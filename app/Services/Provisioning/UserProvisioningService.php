<?php

namespace App\Services\Provisioning;

use App\Enums\User\UserRole;
use App\Models\EmailTemplate;
use App\Models\Mail\SendLoginDetail;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmailTemplate;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Services\Onboarding\OnboardingRedirect;
use App\Services\Profile\ProfileCompletionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The single provisioning path for ordinary company/employee User accounts (UNA-012).
 *
 * Contract:
 * - provision($data) owns a DB::transaction(..., 3) and schedules the result's effects with
 *   DB::afterCommit(), so they run once, only for the attempt that committed.
 * - apply($data) runs inside the caller's transaction (membership services provision an invitee and
 *   create the invitation atomically) and returns the effects for the caller to schedule.
 *
 * Every account ends with: exactly one persona role, the configured default plan, one owned non-POD
 * workspace (ownerable = the user) with an Owner UserWorkspace holding all owner permissions,
 * currant_workspace on an accessible workspace, default email-template rows, a referral code and,
 * for employees, a persisted (empty) Profile. Re-running it for an existing employee repairs
 * missing structure idempotently and never duplicates it.
 *
 * Duplicates: registration sources refuse an existing email (DuplicateRegistration), administrator
 * and CSV sources refuse it too (ProvisioningConflict) - nothing is changed. Invitations reuse an
 * existing employee and refuse companies, administrators and unclassified accounts
 * (IncompatibleInvitee). The unique users.email index settles concurrent creation; only that
 * violation is translated, other database errors propagate.
 */
class UserProvisioningService
{
    public const ATTEMPTS = 3;
    private const POD_MORPH = 'App\\Models\\Pod';

    public function __construct(
        private ProfileCompletionService $profiles,
        private BusinessOnboardingCompletionService $companies,
        private OnboardingRedirect $onboardingRedirect,
    ) {
    }

    public function provision(ProvisionUserData $data): ProvisioningResult
    {
        $result = DB::transaction(fn () => $this->apply($data), self::ATTEMPTS);

        foreach ($result->effects() as $name => $effect) {
            DB::afterCommit(function () use ($effect) {
                try {
                    $effect();
                } catch (\Throwable $e) {
                    report($e); // e.g. SMTP; the account itself is committed
                }
            });
        }

        return $result;
    }

    /** Provision inside the caller's open transaction. The caller schedules $result->effects(). */
    public function apply(ProvisionUserData $data): ProvisioningResult
    {
        $role = $this->resolveRole($data);
        $plan = Plan::find((int) config('provisioning.default_plan_id', 1)) ?? throw new MissingDefaultPlan();

        $existing = $this->findByEmail($data->email);
        if ($existing) {
            return $this->reuse($existing, $data, $role, $plan);
        }

        $password = $data->password;
        $generated = null;
        if ($password === null && $data->generatePassword) {
            $password = $generated = Str::password((int) config('provisioning.generated_password_length', 12), symbols: false);
        }

        try {
            $user = User::createWithAccountRole([
                'name' => trim((string) ($data->name ?: Str::before($data->email, '@'))),
                'email' => $data->email,
                'password' => $password !== null ? Hash::make($password) : null,
                'email_verified_at' => $data->emailVerified ? now() : null,
                'lang' => $data->locale ?: config('provisioning.default_locale', 'en'),
                'is_enable_login' => $data->canLogin ? 1 : 0,
                'referral_used' => $data->referralUsed ?: '0',
            ], $role);
        } catch (UniqueConstraintViolationException $e) {
            // Another request created this email first: treat it exactly like an existing account.
            $winner = $this->findByEmail($data->email) ?? throw $e;

            return $this->reuse($winner, $data, $role, $plan);
        }

        $workspace = $this->ensureStructure($user, $role, $plan, $data);
        $effects = [];
        if ($data->sendLoginDetails) {
            // Self-registered users chose their password: it is never emailed back to them.
            $emailedPassword = $data->source->isSelfRegistration() ? null : $password;
            $effects['login_details_email'] = function () use ($user, $emailedPassword) {
                Utility::setMailConfig();
                Mail::to($user->email)->send(new SendLoginDetail($user, $emailedPassword));
            };
        }

        return new ProvisioningResult($user->fresh(), true, $data->source, $role, $workspace, $this->onboarding($user->fresh()), $generated, $effects);
    }

    /** Validate that a source/role combination may be provisioned (also used by FormRequests). */
    public function resolveRole(ProvisionUserData $data): UserRole
    {
        $fixed = $data->source->fixedRole();
        if ($fixed !== null) {
            if ($data->role !== null && $data->role !== $fixed) {
                throw new InvalidProvisioningRole();
            }

            return $fixed;
        }

        return $data->role ?? throw new InvalidProvisioningRole();
    }

    /**
     * Current onboarding of an account, from the onboarding services (never stored).
     *
     * @return array{required:bool, type:?string, route:?string, url:?string, percentage:?int}
     */
    public function onboarding(User $user): array
    {
        $redirect = $this->onboardingRedirect->for($user);
        $type = $user->isEmployee() ? 'employee' : ($user->isCompany() ? 'company' : null);
        $percentage = match ($type) {
            'employee' => $this->profiles->percentage($user),
            'company' => ($workspace = $this->companies->companyWorkspace($user)) ? $this->companies->percentage($workspace) : 0,
            default => null,
        };

        return [
            'required' => $redirect !== null,
            'type' => $type,
            'route' => $redirect['route'] ?? null,
            'url' => $redirect ? route($redirect['route']) : null,
            'percentage' => $percentage,
        ];
    }

    // ------------------------------------------------------------------ internals

    private function findByEmail(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [$email])->orderBy('id')->first();
    }

    /** Existing account for this email: refuse for non-invitations, reuse a compatible employee otherwise. */
    private function reuse(User $user, ProvisionUserData $data, UserRole $role, Plan $plan): ProvisioningResult
    {
        if ($data->source->isSelfRegistration()) {
            throw new DuplicateRegistration();
        }
        if (!$data->source->isInvitation()) {
            throw new ProvisioningConflict();
        }
        if ($user->isAdministrator() || !$user->isEmployee()) {
            throw new IncompatibleInvitee();
        }

        $workspace = $this->ensureStructure($user, $role, $plan, $data);

        return new ProvisioningResult($user->fresh(), false, $data->source, $role, $workspace, $this->onboarding($user->fresh()));
    }

    /**
     * Create or repair the account structure. Locks the user row so concurrent calls for the same
     * account serialize; every step only adds what is missing.
     */
    private function ensureStructure(User $user, UserRole $role, Plan $plan, ProvisionUserData $data): Workspace
    {
        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

        $attributes = [];
        if ($user->plan === null) {
            $attributes['plan'] = $plan->id;
            $attributes['plan_expire_date'] = null; // the default plan does not expire
        }
        if (empty($user->referral_code)) {
            $attributes['referral_code'] = $this->referralCode();
        }

        $workspace = $this->ownedWorkspace($user, $data);
        $membership = UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner', 'is_active' => 1]);
        if ($membership->wasRecentlyCreated || $membership->permissions()->count() === 0) {
            $membership->assignAllPermissionsToCreator();
        }

        if (!$this->hasAccessibleCurrentWorkspace($user)) {
            $attributes['currant_workspace'] = $workspace->id;
        }
        if ($attributes) {
            $user->forceFill($attributes)->save();
        }

        $this->ensureEmailTemplates($user, $workspace);
        if ($role === UserRole::EMPLOYEE && !Profile::where('user_id', $user->id)->exists()) {
            (new Profile())->forceFill(['user_id' => $user->id])->save(); // empty: onboarding fills it
        }

        return $workspace;
    }

    /** The user's one non-POD workspace (legacy rows without ownerable are adopted), or a new one. */
    private function ownedWorkspace(User $user, ProvisionUserData $data): Workspace
    {
        $workspace = Workspace::where('created_by', $user->id)
            ->where(fn ($q) => $q->whereNull('ownerable_type')->orWhere('ownerable_type', '!=', self::POD_MORPH))
            ->orderBy('id')->lockForUpdate()->first();

        if ($workspace) {
            if ($workspace->ownerable_type === null) {
                $workspace->ownerable()->associate($user)->save();
            }

            return $workspace;
        }

        $settings = Utility::getAdminPaymentSetting();
        $workspace = Workspace::create(array_filter([
            'name' => trim((string) ($data->workspaceName ?: $user->name)),
            'created_by' => $user->id,
            'lang' => $user->lang ?: config('provisioning.default_locale', 'en'),
            'currency_code' => config('provisioning.workspace_currency_code', 'USD'),
            'paypal_mode' => config('provisioning.workspace_paypal_mode', 'sandbox'),
            'theme_color' => $settings['color'] ?? null,
            'color_flag' => $settings['color_flag'] ?? null,
        ], fn ($value) => $value !== null));
        $workspace->ownerable()->associate($user)->save();

        return $workspace;
    }

    private function hasAccessibleCurrentWorkspace(User $user): bool
    {
        return $user->currant_workspace !== null && UserWorkspace::where('user_id', $user->id)
            ->where('workspace_id', $user->currant_workspace)->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', \App\Enums\UserWorkspace\Status::ACTIVE->value))
            ->exists();
    }

    private function ensureEmailTemplates(User $user, Workspace $workspace): void
    {
        $have = UserEmailTemplate::where('user_id', $user->id)->pluck('template_id')->all();
        foreach (EmailTemplate::whereNotIn('id', $have)->pluck('id') as $templateId) {
            UserEmailTemplate::create(['template_id' => $templateId, 'user_id' => $user->id, 'workspace_id' => $workspace->id, 'is_active' => 1]);
        }
    }

    private function referralCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}

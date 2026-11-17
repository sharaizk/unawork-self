<?php

namespace App\Services\Company;

use App\Models\User;
use App\Models\Utility;
use App\Models\Workspace;
use App\Models\WorkspaceWorkerRequirement;
use App\Services\Matching\MatchingConfiguration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves the company (business) onboarding form (UNA-010) onto the company's own workspace.
 *
 * One DB::transaction(..., 3) updates the reused workspace columns, the matching/approval defaults,
 * the worker requirements (+ skills) and the onboarding progress. A replacement logo is uploaded
 * first, removed again if the transaction fails, and the previous file is deleted only after commit.
 * The workspace is always resolved from the authenticated company; workspace_id, created_by and
 * ownerable_* are never taken from input, and requirement ids must belong to this workspace.
 */
class BusinessOnboardingService
{
    private const ATTEMPTS = 3;
    public const LOGO_DIR = 'logo';

    /** Workspace columns the form may write. */
    public const FIELDS = [
        'company', 'industry_id', 'address', 'city', 'state', 'zipcode', 'country', 'telephone',
        'description', 'website', 'linkedin', 'facebook',
        'contact_name', 'contact_email', 'contact_position',
        'matching_mode', 'default_pod_application_approval', 'default_inner_circle_approval',
    ];

    /** Guided steps, in order (the last saved one is stored as business_onboarding_step). */
    public const STEPS = ['company', 'contact', 'online', 'description', 'requirements', 'matching', 'approvals', 'review'];

    public const REQUIREMENT_FIELDS = [
        'job_title_id', 'headcount', 'minimum_years_experience', 'minimum_skill_proficiency',
        'preferred_work_mode', 'preferred_engagement_type', 'location', 'description', 'is_active',
        // UNA-013 matching targets (mandatory_criteria is handled by MatchingConfiguration::withMandatory()).
        'required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type',
    ];

    public function __construct(private BusinessOnboardingCompletionService $completion)
    {
    }

    /** The authenticated company's own, non-POD workspace. */
    public function companyWorkspaceFor(User $user): Workspace
    {
        return $this->completion->companyWorkspace($user)
            ?? throw new AuthorizationException(__('Only a company can manage a company profile.'));
    }

    /** Record that onboarding began (first visit), without changing anything else. */
    public function markStarted(Workspace $workspace): void
    {
        if ($workspace->business_onboarding_started_at === null) {
            Workspace::whereKey($workspace->id)->whereNull('business_onboarding_started_at')
                ->update(['business_onboarding_started_at' => now()]);
            $workspace->business_onboarding_started_at = now();
        }
    }

    public function save(User $user, array $data, ?UploadedFile $logo = null, ?string $step = null): Workspace
    {
        $workspace = $this->companyWorkspaceFor($user);
        $newLogo = $logo ? $this->uploadLogo($workspace, $logo) : null;

        try {
            [$saved, $previousLogo] = DB::transaction(function () use ($user, $workspace, $data, $newLogo, $step) {
                $locked = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
                if ((int) $locked->created_by !== (int) $user->id || $locked->ownerable_type === BusinessOnboardingCompletionService::POD_MORPH) {
                    throw new AuthorizationException(__('Only a company can manage a company profile.'));
                }
                $previousLogo = $locked->logo;

                $locked->fill(Arr::only($data, self::FIELDS));
                if ($newLogo !== null) {
                    $locked->logo = $newLogo;
                }
                $locked->business_onboarding_started_at ??= now();
                if ($step !== null && in_array($step, self::STEPS, true)) {
                    $locked->business_onboarding_step = $step;
                }
                $locked->save();

                if (!empty($data['requirements_present'])) {
                    $this->syncRequirements($locked, $data['requirements'] ?? []);
                }

                $locked->unsetRelation('workerRequirements');
                if ($locked->business_onboarding_completed_at === null && $this->completion->isComplete($locked)) {
                    $locked->forceFill(['business_onboarding_completed_at' => now()])->save();
                }

                return [$locked, $previousLogo];
            }, self::ATTEMPTS);
        } catch (\Throwable $e) {
            if ($newLogo !== null) {
                $this->deleteLogo($workspace, $newLogo);
            }
            throw $e;
        }

        if ($newLogo !== null && $previousLogo && $previousLogo !== $newLogo) {
            $this->deleteLogo($saved, $previousLogo);
        }

        return $saved->fresh(['industry', 'workerRequirements.skills']);
    }

    // ------------------------------------------------------------------ internals

    /** Replace the workspace's requirements with the submitted rows (update by id, create, delete the rest). */
    private function syncRequirements(Workspace $workspace, array $rows): void
    {
        $existing = WorkspaceWorkerRequirement::where('workspace_id', $workspace->id)->lockForUpdate()->get()->keyBy('id');
        $keep = [];

        foreach ($rows as $index => $row) {
            $attributes = MatchingConfiguration::withMandatory(Arr::only($row, self::REQUIREMENT_FIELDS), $row);
            $attributes['is_active'] = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($id !== null) {
                // Validation already rejects foreign ids; this is the last line of defence.
                $requirement = $existing->get($id) ?? throw new AuthorizationException(__('This worker requirement does not belong to your company.'));
                $requirement->fill($attributes)->save();
            } else {
                $requirement = new WorkspaceWorkerRequirement($attributes);
                $requirement->forceFill(['workspace_id' => $workspace->id])->save();
            }
            $requirement->skills()->sync(array_values(array_unique(array_map('intval', $row['skill_ids'] ?? []))));
            // UNA-013: only configured criteria may be mandatory (rolls the whole save back otherwise).
            MatchingConfiguration::assertValid($requirement, "requirements.{$index}.mandatory_criteria");
            $keep[] = $requirement->id;
        }

        WorkspaceWorkerRequirement::where('workspace_id', $workspace->id)->whereNotIn('id', $keep)->get()
            ->each(function (WorkspaceWorkerRequirement $requirement) {
                $requirement->skills()->detach();
                $requirement->delete();
            });
    }

    private function uploadLogo(Workspace $workspace, UploadedFile $logo): string
    {
        $name = $workspace->id . '_company-logo-' . Str::lower(Str::random(12)) . '.' . strtolower($logo->guessExtension() ?: $logo->getClientOriginalExtension() ?: 'png');
        Utility::store_file(self::LOGO_DIR, $logo, $name, $this->disk());

        return $name;
    }

    /** Delete a logo file only if it belongs to this workspace (never a shared default). */
    private function deleteLogo(Workspace $workspace, string $name): void
    {
        if (!str_starts_with($name, $workspace->id . '_') || str_contains($name, '/')) {
            return;
        }
        try {
            Storage::disk($this->disk())->delete(self::LOGO_DIR . '/' . $name);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function disk(): string
    {
        return Utility::getAdminPaymentSetting()['storage_setting'] ?? 'public';
    }
}

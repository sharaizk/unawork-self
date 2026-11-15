<?php

namespace Tests\Feature\Provisioning;

use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Status;
use App\Models\Mail\SendLoginDetail;
use App\Models\Pod;
use App\Models\Project;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\Membership\ProjectMembershipService;
use App\Services\Membership\WorkspaceMembershipService;
use App\Services\PodService;
use App\Services\PodUserService;
use App\Services\Provisioning\DuplicateInvitation;
use App\Services\Provisioning\DuplicateRegistration;
use App\Services\Provisioning\IncompatibleInvitee;
use App\Services\Provisioning\MissingDefaultPlan;
use App\Services\Provisioning\ProvisionUserData;
use App\Services\Provisioning\ProvisioningSource;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** UNA-012: atomicity, after-commit effects, duplicates, incompatible invitees, races and bypass paths. */
class ProvisioningSafetyTest extends ProvisioningTestCase
{
    private User $company;
    private Workspace $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->account('company', 'owner@example.test');
        $this->target = $this->company->ownWorkspace();
    }

    // ================================================================ atomicity + effects

    public function test_failed_workspace_invitation_leaves_no_user_or_membership_and_sends_nothing(): void
    {
        $before = $this->counts();
        $this->failOn('user_email_templates');

        try {
            $this->workspaceInvite('new@example.test', sendLoginDetails: true);
            $this->fail('invited');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->counts());
        Mail::assertNothingSent();
    }

    public function test_failed_pod_invitation_leaves_no_user_membership_access_or_history(): void
    {
        $this->completeBusinessProfile($this->company);
        $pod = app(PodService::class)->createDraft($this->company->fresh(), ['title' => 'Pod']);
        $before = $this->counts();
        $this->failOn('pod_user_state_histories');

        try {
            app(PodUserService::class)->inviteNewUser(['name' => 'Pat', 'email' => 'pat@example.test', 'password' => 'secret12'], $pod->id, [], $this->company);
            $this->fail('invited');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->counts());
        Mail::assertNothingSent();
    }

    public function test_failed_project_invitation_leaves_no_user_or_memberships(): void
    {
        $project = $this->project();
        $before = $this->counts();
        $this->failOn('user_projects');

        try {
            app(ProjectMembershipService::class)->inviteByEmail('pia@example.test', $project, 'Member', $this->company);
            $this->fail('invited');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->counts());
        Mail::assertNothingSent();
    }

    public function test_effects_run_after_commit_and_a_retried_transaction_sends_them_once(): void
    {
        // The first attempt dies with a deadlock after the owner membership was written; DB::transaction retries.
        $failed = false;
        DB::listen(function ($query) use (&$failed) {
            if (!$failed && str_starts_with($query->sql, 'insert into "user_email_templates"')) {
                $failed = true;
                throw new QueryException('sqlite', $query->sql, [], new \PDOException('Deadlock found when trying to get lock; try restarting transaction'));
            }
        });

        $result = $this->workspaceInvite('retry@example.test', sendLoginDetails: true);

        $this->assertTrue($failed, 'the first attempt failed');
        $this->assertTrue($result->applied);
        $this->assertSame(1, User::where('email', 'retry@example.test')->count());
        $this->assertSame(1, Workspace::where('created_by', User::where('email', 'retry@example.test')->value('id'))->count());
        Mail::assertSent(SendLoginDetail::class, 1);
        $this->assertSame(1, DB::table('notifications')->where('type', 'workspace_invite')->count());
    }

    // ================================================================ duplicates

    public function test_duplicate_registration_does_not_mutate_the_existing_account(): void
    {
        $existing = $this->account('employee', 'taken@example.test');
        $before = [$existing->password, $existing->getRoleNames()->all(), $existing->plan, Workspace::where('created_by', $existing->id)->count(), (string) $existing->email_verified_at];

        $this->post('/register', ['name' => 'X', 'email' => '  TAKEN@example.test ', 'password' => 'Other123!', 'password_confirmation' => 'Other123!', 'role' => 'company'])
            ->assertSessionHasErrors(['email' => __('An account with this email address already exists. Please sign in or reset your password.')]);
        $this->postJson('/api/register', ['name' => 'X', 'email' => 'Taken@Example.test', 'password' => 'Other123!', 'password_confirmation' => 'Other123!'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->expectException(DuplicateRegistration::class);
        try {
            $this->provisioning()->provision(new ProvisionUserData(ProvisioningSource::WEB_REGISTRATION, 'TAKEN@example.test', 'X', UserRole::COMPANY, 'Other123!'));
        } finally {
            $existing->refresh();
            $this->assertSame($before, [$existing->password, $existing->getRoleNames()->all(), $existing->plan, Workspace::where('created_by', $existing->id)->count(), (string) $existing->email_verified_at]);
            $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['taken@example.test'])->count());
        }
    }

    public function test_invitations_reuse_a_compatible_employee_and_repair_missing_structure(): void
    {
        // A legacy employee with no plan, workspace or profile.
        $legacy = User::factory()->employee()->create(['email' => 'legacy@example.test', 'plan' => null]);

        $result = $this->workspaceInvite('Legacy@Example.test');

        $this->assertTrue($result->applied);
        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['legacy@example.test'])->count());
        $this->assertAccountStructure($legacy, UserRole::EMPLOYEE, 'my-profile.edit');

        // Inviting again (another workspace) adds only the membership.
        $other = $this->account('company', 'other@example.test');
        app(WorkspaceMembershipService::class)->invite($other->ownWorkspace(), $other, new ProvisionUserData(ProvisioningSource::WORKSPACE_INVITATION, 'legacy@example.test'));
        $this->assertSame(1, Workspace::where('created_by', $legacy->id)->count(), 'no second owned workspace');
        $this->assertSame(3, UserWorkspace::where('user_id', $legacy->id)->count());
    }

    public function test_repeated_invitations_never_reset_state_or_repeat_effects(): void
    {
        $this->workspaceInvite('ivy@example.test', sendLoginDetails: true);
        $ivy = User::where('email', 'ivy@example.test')->sole();
        UserWorkspace::where('user_id', $ivy->id)->where('workspace_id', $this->target->id)->update(['status' => Status::ACTIVE->value]);
        $notifications = DB::table('notifications')->count();

        $again = $this->workspaceInvite('ivy@example.test', sendLoginDetails: true);

        $this->assertFalse($again->applied);
        $this->assertSame('already_member', $again->reason);
        $this->assertSame(Status::ACTIVE->value, UserWorkspace::where('user_id', $ivy->id)->where('workspace_id', $this->target->id)->value('status'));
        $this->assertSame($notifications, DB::table('notifications')->count());
        Mail::assertSent(SendLoginDetail::class, 1);

        // POD: a repeated invitation is reported, not repeated.
        $this->completeBusinessProfile($this->company);
        $pod = app(PodService::class)->createDraft($this->company->fresh(), ['title' => 'Pod']);
        app(PodUserService::class)->inviteNewUser(['name' => 'Ivy', 'email' => 'ivy@example.test'], $pod->id, [], $this->company);
        $histories = DB::table('pod_user_state_histories')->count();
        try {
            app(PodUserService::class)->inviteNewUser(['name' => 'Ivy', 'email' => 'IVY@example.test'], $pod->id, [], $this->company);
            $this->fail('invited twice');
        } catch (DuplicateInvitation) {
            $this->assertSame($histories, DB::table('pod_user_state_histories')->count());
        }
        $this->actingAs($this->company)->postJson("/api/pod/{$pod->id}/users/new", ['name' => 'Ivy', 'email' => 'ivy@example.test'])->assertStatus(409);
    }

    public function test_companies_administrators_and_unclassified_accounts_are_never_invited_or_converted(): void
    {
        $otherCompany = $this->account('company', 'boss@example.test');
        $admin = $this->account('administrator', 'admin@example.test');
        $unclassified = User::factory()->create(['email' => 'nobody@example.test']);
        $before = $this->counts();

        foreach (['boss@example.test', 'ADMIN@example.test', 'nobody@example.test'] as $email) {
            try {
                $this->workspaceInvite($email);
                $this->fail("invited {$email}");
            } catch (IncompatibleInvitee) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame($before, $this->counts());
        $this->assertTrue($otherCompany->fresh()->isCompany());
        $this->assertTrue($admin->fresh()->isAdministrator());
        $this->assertSame([], $unclassified->fresh()->getRoleNames()->all());
        $this->actingAs($this->company)->post(route('users.invite.update', $this->target->slug), ['username' => 'B', 'useremail' => 'boss@example.test'])
            ->assertOk()->assertJsonPath('code', 400)->assertJsonPath('error', __('This email belongs to an account that cannot be invited as an employee.'));
    }

    // ================================================================ races + normalization

    public function test_concurrent_creation_yields_one_user_and_one_owned_workspace(): void
    {
        // Simulate another request inserting the same email between our check and our insert.
        $this->racingEmployee('race@example.test');

        $result = $this->workspaceInvite('race@example.test');

        $this->assertTrue($result->applied);
        $this->assertSame(1, User::where('email', 'race@example.test')->count());
        $user = User::where('email', 'race@example.test')->sole();
        $this->assertAccountStructure($user, UserRole::EMPLOYEE, 'my-profile.edit');

        // The other order: an invitation created the employee first, so registration reports a duplicate.
        $this->racingEmployee('race2@example.test');
        $this->expectException(DuplicateRegistration::class);
        $this->provisioning()->provision(new ProvisionUserData(ProvisioningSource::WEB_REGISTRATION, 'race2@example.test', 'R', UserRole::EMPLOYEE, 'Secret123!'));
    }

    public function test_email_variants_resolve_to_one_normalized_account(): void
    {
        $this->post('/register', ['name' => 'Mixed', 'email' => '  Mixed.Case@Example.TEST ', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'role' => 'employee']);
        auth()->logout();

        $this->assertSame(['mixed.case@example.test'], User::where('email', 'like', '%example.test')->where('name', 'Mixed')->pluck('email')->all());
        $this->workspaceInvite('MIXED.case@example.TEST');
        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['mixed.case@example.test'])->count());
    }

    public function test_a_missing_default_plan_rolls_back_everything(): void
    {
        config(['provisioning.default_plan_id' => 999]);
        $before = $this->counts();

        foreach ([
            fn () => $this->provisioning()->provision(new ProvisionUserData(ProvisioningSource::WEB_REGISTRATION, 'a@example.test', 'A', UserRole::COMPANY, 'Secret123!')),
            fn () => $this->workspaceInvite('b@example.test'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('provisioned without a plan');
            } catch (MissingDefaultPlan) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame($before, $this->counts());
    }

    // ================================================================ CSV import

    public function test_csv_rejects_duplicates_and_admin_rows_before_writing_and_never_overwrites(): void
    {
        $admin = $this->account('administrator', 'admin@example.test');
        $existing = $this->account('employee', 'existing@example.test');
        $hash = $existing->password;
        $before = $this->counts();

        $import = fn (string $csv) => $this->actingAs($admin)->post(route('user.import'), ['file' => UploadedFile::fake()->createWithContent('u.csv', "name,email,password,type\n" . $csv)]);

        $import("A,dup@example.test,x,company\nB, DUP@example.test ,x,employee\n")->assertSessionHas('error');
        $import("A,new@example.test,x,company\nAdmin,admin@example.test,x,company\n")->assertSessionHas('error');
        $import("A,new@example.test,x,administrator\n")->assertSessionHas('error');
        $this->assertSame($before, $this->counts(), 'nothing written for an invalid file');

        $import("Existing,existing@example.test,newpass,company\nNew,new@example.test,secret12,employee\n")->assertSessionHas('success');
        $existing->refresh();
        $this->assertSame([$hash, ['employee']], [$existing->password, $existing->getRoleNames()->all()], 'existing account untouched');
        $this->assertSame(1, Workspace::where('created_by', $existing->id)->count());
        $this->assertAccountStructure(User::where('email', 'new@example.test')->sole(), UserRole::EMPLOYEE, 'my-profile.edit');
    }

    // ================================================================ bypass paths

    public function test_no_production_code_creates_ordinary_users_directly(): void
    {
        $allowed = ['app/Services/Provisioning/UserProvisioningService.php', 'app/Models/User.php'];
        $patterns = '/User::create\(|User::forceCreate\(|createWithAccountRole\(|new User\(|User::firstOrCreate\(|User::updateOrCreate\(|->assignAccountRole\(/';
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app')));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace(base_path() . '/', '', $file->getPathname());
            if (in_array($relative, $allowed, true)) {
                continue;
            }
            $code = preg_replace('#//.*$|/\*.*?\*/#ms', '', file_get_contents($file->getPathname())); // ignore comments
            if (preg_match($patterns, $code)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders);
    }

    // ================================================================ helpers

    private function workspaceInvite(string $email, bool $sendLoginDetails = false)
    {
        return app(WorkspaceMembershipService::class)->invite($this->target, $this->company, new ProvisionUserData(
            source: ProvisioningSource::WORKSPACE_INVITATION,
            email: $email,
            name: 'Invitee',
            password: $sendLoginDetails ? 'secret12' : null,
            sendLoginDetails: $sendLoginDetails,
        ));
    }

    private function project(): Project
    {
        return Project::forceCreate(['name' => 'Apollo', 'workspace' => $this->target->id, 'created_by' => $this->company->id, 'is_active' => 1]);
    }

    /**
     * Simulate a concurrent request that commits the same email right after our existence check
     * (inserted at the outer transaction level, as another connection's committed row would be).
     */
    private function racingEmployee(string $email): void
    {
        $done = false;
        DB::listen(function ($query) use ($email, &$done) {
            if ($done || !str_contains($query->sql, 'LOWER(email) = ?') || ($query->bindings[0] ?? null) !== $email) {
                return;
            }
            $done = true;
            $id = DB::table('users')->insertGetId(['name' => 'Winner', 'email' => $email, 'type' => 'user', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', 'employee')->value('id'), 'model_type' => User::class, 'model_id' => $id]);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function failOn(string $table): void
    {
        DB::statement("CREATE TRIGGER fail_{$table} BEFORE INSERT ON {$table} BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
    }

    private function counts(): array
    {
        return collect(['users', 'workspaces', 'user_workspaces', 'model_has_roles', 'model_has_permissions', 'profiles', 'user_email_templates', 'pod_users', 'pod_user_state_histories', 'user_projects', 'notifications'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }
}

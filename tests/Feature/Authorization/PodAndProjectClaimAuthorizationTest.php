<?php

namespace Tests\Feature\Authorization;

use App\Enums\PodUser\Permission as PodUserPermission;
use App\Enums\PodUser\Status as PodUserStatus;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Policies\PodPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SkillPolicy;
use App\Policies\UserProjectPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * UNA-001: POD membership and project claim authorization.
 *
 * Safety: this class never touches the database configured in .env. It forces an
 * in-memory SQLite connection before the application boots and builds only the
 * tables these policies need (the real migrations are MySQL-specific). setUp()
 * aborts if the connection is anything other than in-memory SQLite.
 *
 * Allowed paths with heavy side effects (notifications/Pusher/mail) are asserted at
 * the policy level; denied paths are asserted over HTTP (403 + no mutation).
 */
class PodAndProjectClaimAuthorizationTest extends TestCase
{
    private static array $originalEnv = [];

    private Pod $pod;
    private User $owner;
    private User $manager;
    private User $member;
    private User $applicant;
    private User $invitee;
    private User $outsider;

    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DATABASE_URL' => ''] as $key => $value) {
            if (!array_key_exists($key, self::$originalEnv)) {
                self::$originalEnv[$key] = $_SERVER[$key] ?? null;
            }
            $_SERVER[$key] = $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.url', null);

        return $app;
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$originalEnv as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key], $_ENV[$key]);
                putenv($key);
            } else {
                $_SERVER[$key] = $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
        self::$originalEnv = [];

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: authorization tests must use an in-memory SQLite database.');
        }

        $this->createSchema();
        $this->seedPermissions();

        $this->owner = $this->makeUser('owner');
        $this->manager = $this->makeUser('manager');
        $this->member = $this->makeUser('member');
        $this->applicant = $this->makeUser('applicant');
        $this->invitee = $this->makeUser('invitee');
        $this->outsider = $this->makeUser('outsider');

        $this->pod = $this->makePod($this->owner);
        $this->addMember($this->pod, $this->manager, PodUserStatus::ACTIVE, WorkspaceStatus::ACTIVE, [
            WorkspacePermission::USERS_INDEX, WorkspacePermission::USERS_EDIT,
        ]);
        $this->addMember($this->pod, $this->member, PodUserStatus::ACTIVE, WorkspaceStatus::ACTIVE, [
            WorkspacePermission::USERS_INDEX, WorkspacePermission::NEWS_INDEX,
        ]);
        $this->addMember($this->pod, $this->applicant, PodUserStatus::POD_PENDING, WorkspaceStatus::EMPLOYEE_PENDING, [
            WorkspacePermission::USERS_INDEX,
        ]);
        $this->addMember($this->pod, $this->invitee, PodUserStatus::EMPLOYEE_PENDING, WorkspaceStatus::EMPLOYEE_PENDING, [
            WorkspacePermission::USERS_INDEX,
        ]);
    }

    // ---------------------------------------------------------------------
    // Policy registration
    // ---------------------------------------------------------------------

    public function test_policies_resolve_to_the_intended_classes(): void
    {
        $this->assertInstanceOf(PodPolicy::class, Gate::getPolicyFor(Pod::class));
        $this->assertInstanceOf(UserProjectPolicy::class, Gate::getPolicyFor(UserProject::class));
        $this->assertInstanceOf(ProjectPolicy::class, Gate::getPolicyFor(Workspace::class));
        $this->assertInstanceOf(SkillPolicy::class, Gate::getPolicyFor(Skill::class));
    }

    // ---------------------------------------------------------------------
    // 1. Owner
    // ---------------------------------------------------------------------

    public function test_owner_can_manage_members_and_inner_circle(): void
    {
        $gate = Gate::forUser($this->owner);

        $this->assertTrue($gate->allows('viewMembers', $this->pod));
        $this->assertTrue($gate->allows('manageMembers', $this->pod));
        $this->assertTrue($gate->allows('acceptMember', [$this->pod, $this->applicant]));
        $this->assertTrue($gate->allows('declineMember', [$this->pod, $this->applicant]));
        $this->assertTrue($gate->allows('removeMember', [$this->pod, $this->member]));
        $this->assertTrue($gate->allows('updateMemberPermissions', [$this->pod, $this->member]));
        $this->assertTrue($gate->allows('toggleInnerCircle', $this->pod));
        $this->assertTrue($gate->allows('moderateJoinRequest', [$this->pod, $this->podUser($this->pod, $this->applicant)]));
    }

    public function test_owner_can_disable_inner_circle_over_http(): void
    {
        $this->pod->forceFill(['inner_enabled' => 1])->save();

        $this->actingAs($this->owner)
            ->get(route('pods.disableInnerCircle', $this->pod->id))
            ->assertRedirect();

        $this->assertFalse((bool) $this->pod->fresh()->inner_enabled);
    }

    // ---------------------------------------------------------------------
    // 2. Manager with users_edit
    // ---------------------------------------------------------------------

    public function test_manager_with_users_edit_can_manage_members_but_not_owner_or_inner_circle_toggle(): void
    {
        $gate = Gate::forUser($this->manager);

        $this->assertTrue($gate->allows('manageMembers', $this->pod));
        $this->assertTrue($gate->allows('acceptMember', [$this->pod, $this->applicant]));
        $this->assertTrue($gate->allows('declineMember', [$this->pod, $this->applicant]));
        $this->assertTrue($gate->allows('removeMember', [$this->pod, $this->member]));
        $this->assertTrue($gate->allows('updateMemberPermissions', [$this->pod, $this->member]));

        $this->assertFalse($gate->allows('removeMember', [$this->pod, $this->owner]));
        $this->assertFalse($gate->allows('updateMemberPermissions', [$this->pod, $this->owner]));
        $this->assertFalse($gate->allows('toggleInnerCircle', $this->pod));
        // Invitations are answered by the invitee, never on their behalf.
        $this->assertFalse($gate->allows('acceptMember', [$this->pod, $this->invitee]));
    }

    public function test_manager_can_moderate_a_join_request_of_their_pod_over_api(): void
    {
        $request = $this->podUser($this->pod, $this->applicant);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pods/{$this->pod->id}/requests/{$request->id}", ['action' => 'accept'])
            ->assertOk();

        $this->assertSame(PodUserStatus::ACTIVE->value, $request->fresh()->status);
    }

    public function test_manager_without_active_workspace_membership_is_not_a_manager(): void
    {
        UserWorkspace::where('user_id', $this->manager->id)->update(['status' => WorkspaceStatus::DECLINED->value]);

        $this->assertFalse(Gate::forUser($this->manager)->allows('manageMembers', $this->pod));
    }

    // ---------------------------------------------------------------------
    // 3. Ordinary member / 4. Outsider (HTTP 403 + no mutation)
    // ---------------------------------------------------------------------

    public function test_ordinary_member_is_denied_member_management(): void
    {
        $this->assertManagementDenied($this->member);
    }

    public function test_unrelated_user_is_denied_member_management(): void
    {
        $this->assertManagementDenied($this->outsider);

        $this->actingAs($this->outsider)
            ->getJson(route('pods.users', ['podId' => $this->pod->id]))
            ->assertForbidden();
    }

    public function test_api_invite_new_user_authorizes_before_validation(): void
    {
        // An existing email would fail `unique` with 422; authorization must win so outsiders
        // cannot probe registered emails.
        $this->actingAs($this->outsider, 'sanctum')
            ->postJson("/api/pod/{$this->pod->id}/users/new", ['email' => $this->member->email, 'name' => 'x', 'password' => 'x'])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // 5. Invitations are self-service
    // ---------------------------------------------------------------------

    public function test_invitee_can_answer_only_their_own_invitation(): void
    {
        $this->assertTrue(Gate::forUser($this->invitee)->allows('acceptMember', [$this->pod, $this->invitee]));
        $this->assertTrue(Gate::forUser($this->invitee)->allows('declineMember', [$this->pod, $this->invitee]));
        $this->assertFalse(Gate::forUser($this->owner)->allows('acceptMember', [$this->pod, $this->invitee]));

        // Answering someone else's invitation/request is denied and changes nothing.
        $other = $this->makeUser('other-invitee');
        $this->addMember($this->pod, $other, PodUserStatus::EMPLOYEE_PENDING, WorkspaceStatus::EMPLOYEE_PENDING);

        $this->actingAs($this->invitee)
            ->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $other]))
            ->assertForbidden();
        $this->actingAs($this->invitee)
            ->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $this->applicant]))
            ->assertForbidden();

        $this->assertSame(PodUserStatus::EMPLOYEE_PENDING->value, $this->podUser($this->pod, $other)->status);
        $this->assertSame(PodUserStatus::POD_PENDING->value, $this->podUser($this->pod, $this->applicant)->status);

        // An invitee is not a manager.
        $this->assertFalse(Gate::forUser($this->invitee)->allows('manageMembers', $this->pod));
    }

    // ---------------------------------------------------------------------
    // 6. Leaving
    // ---------------------------------------------------------------------

    public function test_member_can_leave_but_owner_cannot(): void
    {
        $this->assertTrue(Gate::forUser($this->member)->allows('removeMember', [$this->pod, $this->member]));
        $this->assertTrue(Gate::forUser($this->invitee)->allows('removeMember', [$this->pod, $this->invitee]));

        $this->actingAs($this->owner)
            ->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $this->owner->id]))
            ->assertForbidden();

        $this->assertNotNull($this->podUser($this->pod, $this->owner));
    }

    // ---------------------------------------------------------------------
    // 7. Cross-POD ID substitution
    // ---------------------------------------------------------------------

    public function test_cross_pod_ids_are_denied(): void
    {
        $otherOwner = $this->makeUser('other-owner');
        $otherPod = $this->makePod($otherOwner);
        $otherApplicant = $this->makeUser('other-applicant');
        $otherMember = $this->makeUser('other-member');
        $this->addMember($otherPod, $otherApplicant, PodUserStatus::POD_PENDING, WorkspaceStatus::EMPLOYEE_PENDING);
        $this->addMember($otherPod, $otherMember, PodUserStatus::ACTIVE, WorkspaceStatus::ACTIVE);
        $otherRequest = $this->podUser($otherPod, $otherApplicant);

        // API: our POD id + another POD's request id.
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/pods/{$this->pod->id}/requests/{$otherRequest->id}", ['action' => 'accept'])
            ->assertForbidden();
        $this->assertSame(PodUserStatus::POD_PENDING->value, $otherRequest->fresh()->status);

        // Web: accept a user who has no membership in this POD (previously created a row).
        $this->actingAs($this->owner)
            ->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $otherApplicant]))
            ->assertForbidden();
        $this->assertNull($this->podUser($this->pod, $otherApplicant));

        // Web: remove another POD's member through our POD.
        $this->actingAs($this->owner)
            ->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $otherMember->id]))
            ->assertForbidden();
        $this->assertNotNull($this->podUser($otherPod, $otherMember));

        // Web: edit permissions of a non-member.
        $this->actingAs($this->owner)
            ->putJson(route('pod.users.updatePermissions', ['pod' => $this->pod, 'user' => $otherMember]), ['permissions' => ['users_edit']])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // 8-12. Project claims
    // ---------------------------------------------------------------------

    public function test_project_claim_moderation_matrix(): void
    {
        [$project, $claim] = $this->makeProjectClaim($this->member);

        $projectManager = $this->makeUser('project-manager');
        $this->addMember($this->pod, $projectManager, PodUserStatus::ACTIVE, WorkspaceStatus::ACTIVE, [
            WorkspacePermission::PROJECTS_INDEX, WorkspacePermission::PROJECTS_EDIT,
        ]);

        // 8. Workspace/POD owner.
        $this->assertTrue(Gate::forUser($this->owner)->allows('accept', $claim));
        $this->assertTrue(Gate::forUser($this->owner)->allows('decline', $claim));
        // 9. Active member with projects_edit.
        $this->assertTrue(Gate::forUser($projectManager)->allows('accept', $claim));
        $this->assertTrue(Gate::forUser($projectManager)->allows('decline', $claim));
        // users_edit alone is not project moderation.
        $this->assertFalse(Gate::forUser($this->manager)->allows('accept', $claim));
        // 10. Claimant and outsider.
        $this->assertFalse(Gate::forUser($this->member)->allows('accept', $claim));
        $this->assertFalse(Gate::forUser($this->outsider)->allows('decline', $claim));
    }

    public function test_claimant_and_outsider_cannot_moderate_claim_over_http_and_nothing_changes(): void
    {
        [$project, $claim] = $this->makeProjectClaim($this->member);
        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $this->owner->id,
            'type' => 'project_request',
            'data' => json_encode(['id' => $claim->id]),
            'is_read' => 0,
        ]);

        $this->actingAs($this->member)
            ->getJson(route('pods.projects.join.accept', ['record' => $claim->id]))
            ->assertForbidden();
        $this->actingAs($this->outsider)
            ->getJson(route('pods.projects.join.decline', ['record' => $claim->id]))
            ->assertForbidden();

        $fresh = $claim->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(0, (int) $fresh->is_active);
        $this->assertTrue(DB::table('notifications')->where('id', $notificationId)->exists());
    }

    public function test_eligible_member_can_submit_claim_but_outsider_and_pending_member_cannot(): void
    {
        $project = $this->makeProject($this->pod);

        // 11. Active workspace member.
        $this->assertTrue(Gate::forUser($this->member)->allows('create', [UserProject::class, $project]));

        // 12. Outsider and a not-yet-accepted invitee.
        foreach ([$this->outsider, $this->invitee] as $user) {
            $this->actingAs($user)
                ->getJson(route('pods.projects.join', ['project' => $project->id]))
                ->assertForbidden();
        }

        $this->assertSame(0, UserProject::where('project_id', $project->id)->count());
    }

    // ---------------------------------------------------------------------
    // 13. Administrators
    // ---------------------------------------------------------------------

    public function test_administrator_access_is_preserved_without_a_global_bypass(): void
    {
        $admin = $this->makeUser('admin', ['type' => 'admin']);

        // Existing admin-only abilities still resolve through the corrected mapping.
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Skill::class));
        $this->assertFalse(Gate::forUser($this->owner)->allows('viewAny', Skill::class));

        // An admin who owns a POD keeps full owner rights there.
        $adminPod = $this->makePod($admin);
        $adminMember = $this->makeUser('admin-pod-member');
        $this->addMember($adminPod, $adminMember, PodUserStatus::ACTIVE, WorkspaceStatus::ACTIVE);
        $this->assertTrue(Gate::forUser($admin)->allows('manageMembers', $adminPod));
        $this->assertTrue(Gate::forUser($admin)->allows('removeMember', [$adminPod, $adminMember]));

        // No implicit bypass into PODs the admin has no role in (matches pre-existing behaviour).
        $this->actingAs($admin)
            ->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $this->applicant]))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function assertManagementDenied(User $user): void
    {
        $pod = $this->pod;
        $applicantRequest = $this->podUser($pod, $this->applicant);
        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $this->owner->id,
            'type' => 'pod_request',
            'data' => json_encode(['id' => $applicantRequest->id]),
            'is_read' => 0,
        ]);
        $memberPermissions = $this->permissionsOf($pod, $this->member);

        $web = $this->actingAs($user);
        $web->getJson(route('pods.invite', ['podId' => $pod->id]))->assertForbidden();
        $web->postJson(route('pods.user.exists', ['podId' => $pod->id]), ['email' => $this->outsider->email])->assertForbidden();
        $web->postJson(route('pods.accept', ['pod' => $pod, 'user' => $this->applicant]))->assertForbidden();
        $web->postJson(route('pods.decline', ['pod' => $pod, 'user' => $this->applicant]))->assertForbidden();
        $web->deleteJson(route('pods.remove', ['podId' => $pod->id, 'userId' => $this->manager->id]))->assertForbidden();
        $web->getJson(route('pod.users.editPermissions', ['pod' => $pod, 'user' => $this->member]))->assertForbidden();
        $web->putJson(route('pod.users.updatePermissions', ['pod' => $pod, 'user' => $this->member]), ['permissions' => ['users_edit', 'projects_index']])->assertForbidden();
        $web->getJson(route('pods.enableInnerCircle', $pod->id))->assertForbidden();
        $web->getJson(route('pods.disableInnerCircle', $pod->id))->assertForbidden();

        $api = $this->actingAs($user, 'sanctum');
        $api->getJson("/api/pods/{$pod->id}/requests")->assertForbidden();
        $api->postJson("/api/pod/{$pod->id}/users", ['user_id' => $this->outsider->id])->assertForbidden();
        $api->postJson("/api/pod/{$pod->id}/users/new", ['email' => 'new-' . uniqid() . '@example.test', 'name' => 'x', 'password' => 'x'])->assertForbidden();
        $api->postJson("/api/pods/{$pod->id}/requests/{$applicantRequest->id}", ['action' => 'accept'])->assertForbidden();

        // Nothing changed.
        $this->assertSame(PodUserStatus::POD_PENDING->value, $applicantRequest->fresh()->status);
        $this->assertNotNull($this->podUser($pod, $this->manager));
        $this->assertNull($this->podUser($pod, $this->outsider));
        $this->assertSame($memberPermissions, $this->permissionsOf($pod, $this->member));
        $this->assertFalse((bool) $pod->fresh()->inner_enabled);
        $this->assertTrue(DB::table('notifications')->where('id', $notificationId)->exists());
        $this->assertSame(0, User::where('email', 'like', 'new-%@example.test')->count());
    }

    private function makeUser(string $name, array $attributes = []): User
    {
        return User::forceCreate(array_merge([
            'name' => $name,
            'email' => $name . '-' . uniqid() . '@example.test',
            'password' => 'not-used',
            'type' => 'user',
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function makePod(User $owner): Pod
    {
        $pod = Pod::forceCreate(['title' => 'Pod ' . uniqid(), 'description' => 'd', 'created_by' => $owner->id, 'inner_enabled' => 0]);

        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Workspace ' . $pod->id,
            'slug' => 'workspace-' . $pod->id . '-' . uniqid(),
            'created_by' => $owner->id,
            'ownerable_type' => Pod::class,
            'ownerable_id' => $pod->id,
        ]);

        DB::table('pod_users')->insert([
            'user_id' => $owner->id, 'pod_id' => $pod->id,
            'permission' => PodUserPermission::OWNER->value, 'status' => PodUserStatus::ACTIVE->value,
        ]);
        $userWorkspaceId = DB::table('user_workspaces')->insertGetId([
            'user_id' => $owner->id, 'workspace_id' => $workspaceId,
            'permission' => 'Owner', 'is_active' => 1, 'status' => WorkspaceStatus::ACTIVE->value,
        ]);
        UserWorkspace::find($userWorkspaceId)->givePermissionTo(array_map(fn ($p) => $p->value, WorkspacePermission::cases()));

        return $pod;
    }

    private function addMember(Pod $pod, User $user, PodUserStatus $podStatus, WorkspaceStatus $workspaceStatus, array $permissions = []): void
    {
        DB::table('pod_users')->insert([
            'user_id' => $user->id, 'pod_id' => $pod->id,
            'permission' => PodUserPermission::MEMBER->value, 'status' => $podStatus->value,
        ]);
        $userWorkspaceId = DB::table('user_workspaces')->insertGetId([
            'user_id' => $user->id, 'workspace_id' => $pod->workspace->id,
            'permission' => 'Member', 'is_active' => 1, 'status' => $workspaceStatus->value,
        ]);
        if ($permissions) {
            UserWorkspace::find($userWorkspaceId)->givePermissionTo(array_map(fn ($p) => $p->value, $permissions));
        }
    }

    private function makeProject(Pod $pod): Project
    {
        return Project::forceCreate([
            'name' => 'Project ' . uniqid(),
            'workspace' => $pod->workspace->id,
            'created_by' => $pod->created_by,
            'is_active' => 1,
        ]);
    }

    /** @return array{0: Project, 1: UserProject} */
    private function makeProjectClaim(User $claimant): array
    {
        $project = $this->makeProject($this->pod);
        $claim = UserProject::forceCreate([
            'project_id' => $project->id, 'user_id' => $claimant->id, 'is_active' => 0, 'status' => 'pending',
        ]);

        return [$project, $claim];
    }

    private function podUser(Pod $pod, User $user): ?PodUser
    {
        return PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->first();
    }

    private function permissionsOf(Pod $pod, User $user): array
    {
        $names = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $pod->workspace->id)
            ->first()->getPermissionNames()->sort()->values()->all();

        return $names;
    }

    private function seedPermissions(): void
    {
        foreach (WorkspacePermission::cases() as $permission) {
            Permission::create(['name' => $permission->value, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->rememberToken();
            $t->string('type')->default('user');
            $t->unsignedBigInteger('currant_workspace')->nullable();
            $t->unsignedBigInteger('plan')->nullable();
            $t->string('lang')->nullable();
            $t->string('avatar')->nullable();
            $t->integer('is_enable_login')->default(1);
            $t->timestamps();
        });
        Schema::create('pods', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('image')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->boolean('inner_enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('pod_users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('pod_id');
            $t->string('permission');
            $t->string('status');
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'pod_id'], 'pod_users_user_id_pod_id_unique'); // UNA-004, as in production
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug');
            $t->unsignedBigInteger('created_by');
            $t->string('ownerable_type')->nullable();
            $t->unsignedBigInteger('ownerable_id')->nullable();
            $t->integer('is_active')->default(1);
            $t->string('status')->nullable();
            $t->timestamps();
        });
        Schema::create('user_workspaces', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('workspace_id');
            $t->string('permission');
            $t->integer('is_active')->default(1);
            $t->string('status')->default('active');
            $t->integer('is_enable_login')->default(1);
            $t->timestamps();
            $t->unique(['user_id', 'workspace_id'], 'user_workspaces_user_id_workspace_id_unique'); // UNA-004, as in production
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('workspace');
            $t->unsignedBigInteger('created_by');
            $t->integer('is_active')->default(1);
            $t->timestamps();
        });
        Schema::create('user_projects', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('project_id');
            $t->integer('is_active')->default(1);
            $t->text('permission')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['user_id', 'project_id'], 'user_projects_user_id_project_id_unique'); // UNA-004, as in production
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id')->nullable();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('project_id')->nullable();
            $t->unsignedBigInteger('related_id')->nullable();
            $t->string('type');
            $t->text('data');
            $t->integer('is_read')->default(0);
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });
    }
}

<?php

namespace Tests\Feature\Membership;

use App\Enums\PodUser\State;
use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Jobs\SendEmail;
use App\Models\Mail\SendWorkspaceInvication;
use App\Models\Notification;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\Membership\ProjectMembershipService;
use App\Services\Membership\WorkspaceMembershipService;
use App\Services\PodService;
use App\Services\PodUserService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-005: multi-record membership operations are all-or-nothing and external effects run only
 * after commit. Failures are injected deterministically in the middle of real operations
 * (SQLite triggers that RAISE on a chosen write, or a query listener that throws after a chosen
 * statement) and the complete pre-operation state is compared afterwards. In-memory SQLite only.
 */
class TransactionalMembershipTest extends TestCase
{
    use UsesInMemorySqlite;

    private User $owner;
    private Pod $pod;
    private Workspace $podWorkspace;
    /** @var int[] DB::transactionLevel() observed whenever a notification row was written */
    private array $notificationLevels = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        Schema::create('threads', function (Blueprint $t) {
            $t->id();
            $t->string('subject')->nullable();
            $t->string('slug')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->unsignedBigInteger('project_id');
            $t->string('assign_to')->nullable();
            $t->timestamps();
        });
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Mail::fake();
        Bus::fake();

        $this->owner = $this->registered('owner', UserRole::COMPANY);
        $this->pod = app(PodService::class)->createPod(['title' => 'Pod', 'description' => 'd', 'created_by' => $this->owner->id]);
        $this->podWorkspace = $this->pod->workspace;

        // Returns nothing: a non-null return would halt the remaining `creating` listeners.
        Notification::creating(function () {
            $this->notificationLevels[] = DB::transactionLevel();
        });
    }

    // ================================================================ applications / invitations

    public function test_join_request_failing_after_the_pod_row_leaves_nothing_behind(): void // 1, 6
    {
        $requester = $this->registered('requester', UserRole::EMPLOYEE);
        $this->failOn('BEFORE INSERT', 'user_workspaces');
        $before = $this->snapshot();

        $this->assertFails(fn () => app(PodUserService::class)->requestJoinPod($requester->id, $this->pod->id));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, Notification::count(), 'no owner notification for a rolled-back request');
    }

    public function test_invitation_failing_after_both_rows_rolls_back_both(): void // 2, 6
    {
        $invitee = $this->registered('invitee', UserRole::EMPLOYEE);
        $this->failOn('BEFORE INSERT', 'pod_user_state_histories'); // after pod_users + user_workspaces
        $before = $this->snapshot();

        $this->assertFails(fn () => app(PodUserService::class)->inviteExistedUser($invitee->id, $this->pod->id, []));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, Notification::count());
        Bus::assertNotDispatched(SendEmail::class);
    }

    public function test_failed_new_user_invitation_leaves_no_orphan_user_or_role(): void // 3
    {
        $this->failOn('BEFORE INSERT', 'pod_user_state_histories'); // after user, role and both memberships
        $before = $this->snapshot();

        $this->assertFails(fn () => app(PodUserService::class)->inviteNewUser(['name' => 'New', 'email' => 'new@example.test', 'password' => 'secret123'], $this->pod->id, []));

        $this->assertEquals($before, $this->snapshot());
        $this->assertFalse(User::where('email', 'new@example.test')->exists());
        Bus::assertNotDispatched(SendEmail::class);
    }

    public function test_new_user_invitation_creates_user_pod_and_workspace_membership_together(): void
    {
        app(PodUserService::class)->inviteNewUser(['name' => 'New', 'email' => 'new@example.test', 'password' => 'secret123'], $this->pod->id, []);

        $user = User::where('email', 'new@example.test')->sole();
        $this->assertTrue($user->isEmployee());
        $this->assertSame(State::MATCHED, $this->podMembership($user)->state);
        $this->assertSame([], $this->workspaceMembership($user)->getPermissionNames()->all(), 'invitees have no workspace access yet');
        Bus::assertDispatchedTimes(SendEmail::class, 2);
    }

    public function test_successful_application_creates_both_rows_and_notifies_after_commit(): void // 5
    {
        $requester = $this->registered('requester', UserRole::EMPLOYEE);

        $result = app(PodUserService::class)->requestJoinPod($requester->id, $this->pod->id);

        $this->assertTrue($result->applied);
        $this->assertSame(['notify_owner'], $result->effectNames());
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $this->podMembership($requester)->state);
        $this->assertSame('employee_pending', $this->workspaceMembership($requester)->status);
        $this->assertSame([], $this->workspaceMembership($requester)->getPermissionNames()->all(), 'applicants have no workspace access yet');
        $this->assertSame(1, $this->notifications('pod_request'));
        $this->assertSame([0], $this->notificationLevels, 'the notification was written after the commit');
    }

    // ================================================================ approval / rejection

    public function test_approval_failing_after_the_pod_status_change_rolls_back_both(): void // 7, 12
    {
        $applicant = $this->applicant('applicant');
        $this->failOn('BEFORE UPDATE', 'user_workspaces');
        $before = $this->snapshot();

        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))->assertStatus(500);

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $this->podMembership($applicant)->state);
        $this->assertSame(1, $this->notifications('pod_request'), 'the request stays actionable');
        $this->assertSame(0, $this->notifications('pod_request_accept_user'));
    }

    public function test_approval_failing_on_the_default_permission_grant_rolls_back_everything(): void // 4
    {
        $applicant = $this->applicant('applicant');
        $this->failOn('BEFORE INSERT', 'model_has_permissions'); // the outer-circle baseline on approval
        $before = $this->snapshot();

        $this->assertFails(fn () => app(PodUserService::class)->respondToMembership($this->pod, $applicant, State::OUTER_CIRCLE, $this->owner));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $this->podMembership($applicant)->state);
    }

    public function test_rejection_failing_after_the_workspace_change_rolls_back_the_pod_status(): void // 8, 12
    {
        $applicant = $this->applicant('applicant');
        $this->failOn('BEFORE INSERT', 'pod_user_state_histories'); // runs after the state, workspace and notification changes
        $before = $this->snapshot();

        $this->assertFails(fn () => app(PodUserService::class)->respondToMembership($this->pod, $applicant, State::REJECTED, $this->owner));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame('employee_pending', $this->workspaceMembership($applicant)->status);
        $this->assertSame(0, $this->notifications('pod_request_decline_user'));
    }

    public function test_successful_approval_and_rejection_leave_consistent_rows(): void // 9, 10, 23
    {
        $accepted = $this->applicant('accepted');
        $declined = $this->applicant('declined');
        $declined->forceFill(['currant_workspace' => $this->podWorkspace->id])->save();

        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $accepted]))->assertOk();
        $this->actingAs($this->owner)->postJson(route('pods.decline', ['pod' => $this->pod, 'user' => $declined]))->assertOk();

        $this->assertSame(State::OUTER_CIRCLE, $this->podMembership($accepted)->state);
        $this->assertSame(['active', 1], [$this->workspaceMembership($accepted)->status, (int) $this->workspaceMembership($accepted)->is_active]);
        $this->assertEqualsCanonicalizing(['users_index', 'news_index'], $this->workspaceMembership($accepted)->getPermissionNames()->all());

        $this->assertSame(State::REJECTED, $this->podMembership($declined)->state);
        $this->assertSame(['declined', 0], [$this->workspaceMembership($declined)->status, (int) $this->workspaceMembership($declined)->is_active]);
        $this->assertSame([], $this->workspaceMembership($declined)->getPermissionNames()->all(), 'no access left on a declined membership');
        $this->assertSame($this->ownWorkspaceId($declined), (int) $declined->fresh()->currant_workspace, 'current workspace moved to an accessible one');

        $this->assertSame(0, $this->notifications('pod_request'), 'both actionable requests resolved');
        $this->assertSame(1, $this->notifications('pod_request_accept_user'));
        $this->assertSame(1, $this->notifications('pod_request_decline_user'));
    }

    public function test_racing_accept_and_decline_resolve_exactly_once(): void // 11
    {
        $applicant = $this->applicant('applicant');
        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))->assertOk();

        Gate::before(fn () => true); // a decision that passed authorization before the first one committed
        $this->actingAs($this->owner)->postJson(route('pods.decline', ['pod' => $this->pod, 'user' => $applicant]))->assertStatus(409);

        $this->assertSame(State::OUTER_CIRCLE, $this->podMembership($applicant)->state);
        $this->assertSame('active', $this->workspaceMembership($applicant)->status);
        $this->assertSame(0, $this->notifications('pod_request_decline_user'));
    }

    // ================================================================ Inner Circle

    public function test_failed_promotion_restores_the_original_permission_set(): void // 13
    {
        $member = $this->activeMember('member');
        $this->failOn('BEFORE INSERT', 'model_has_permissions');
        $before = $this->snapshot();

        $this->assertFails(fn () => app(PodUserService::class)->updatePermissions($this->pod, $member, ['users_index', 'news_index'], State::INNER_CIRCLE, $this->owner));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, $this->notifications('pod_inner_circle_invite'));
    }

    public function test_promotion_grants_the_inner_circle_set_and_notifies_after_commit(): void // 14, 17
    {
        $member = $this->activeMember('member');

        $result = app(PodUserService::class)->updatePermissions($this->pod, $member, [], State::INNER_CIRCLE, $this->owner);

        $this->assertSame(['notify_inner_circle'], $result->effectNames());
        $this->assertEqualsCanonicalizing(['users_index', 'news_index', 'projects_index'], $this->workspaceMembership($member)->getPermissionNames()->all(), 'outer-circle access is kept');
        $this->assertTrue(Gate::forUser($member)->allows('viewInner', $this->pod), 'UI state and project access agree');
        $this->assertSame(1, $this->notifications('pod_inner_circle_invite'));
        $this->assertSame([0], $this->notificationLevels);

        // A pending member cannot be promoted.
        $pending = $this->applicant('pending');
        $this->assertFails(fn () => app(PodUserService::class)->updatePermissions($this->pod, $pending, [], State::INNER_CIRCLE, $this->owner)); // not in the transition graph
        $this->assertFalse($this->workspaceMembership($pending)->hasPermissionTo('projects_index'));
    }

    public function test_demotion_removes_inner_circle_access_but_keeps_independent_project_access(): void // 15
    {
        $member = $this->activeMember('member', ['projects_index', 'projects_edit', 'users_edit']);
        [$project, $task] = $this->projectWithTaskFor($member);

        $result = app(PodUserService::class)->updatePermissions($this->pod, $member, ['users_edit', 'projects_edit'], State::OUTER_CIRCLE, $this->owner, 'Back to the Outer Circle');

        $this->assertSame([], $result->effectNames());
        // projects_edit cannot exist without projects_index; outer-circle permissions stay.
        $this->assertEqualsCanonicalizing(['users_edit', 'users_index', 'news_index'], $this->workspaceMembership($member)->getPermissionNames()->all());
        $this->assertFalse(Gate::forUser($member)->allows('viewInner', $this->pod));
        // Project memberships are granted by claims/invitations, not by the circle: they survive.
        $this->assertTrue(UserProject::where('user_id', $member->id)->where('project_id', $project->id)->exists());
        $this->assertSame("{$this->owner->id},{$member->id}", $task->fresh()->assign_to);
    }

    public function test_failed_demotion_restores_permissions_and_project_access(): void // 16
    {
        $member = $this->activeMember('member', ['projects_index', 'projects_edit']);
        $this->projectWithTaskFor($member);
        $this->failOn('BEFORE INSERT', 'model_has_permissions'); // sync: detach succeeds, re-attach fails
        $before = $this->snapshot();

        $this->assertFails(fn () => app(PodUserService::class)->updatePermissions($this->pod, $member, ['users_index'], State::OUTER_CIRCLE, $this->owner, 'Back to the Outer Circle'));

        $this->assertEquals($before, $this->snapshot());
        $this->assertTrue($this->workspaceMembership($member)->hasPermissionTo('projects_index'));
    }

    // ================================================================ removal / leave

    public static function removalFailurePoints(): array
    {
        return [
            'after task assignments changed (18)' => ['BEFORE DELETE', 'user_projects'],
            'after project membership removed (19)' => ['BEFORE DELETE', 'user_workspaces'],
            'after workspace membership removed (20)' => ['BEFORE INSERT', 'pod_user_state_histories'],
        ];
    }

    #[DataProvider('removalFailurePoints')]
    public function test_failed_removal_restores_everything(string $timing, string $table): void
    {
        $member = $this->activeMember('member', ['projects_index']);
        $this->workspaceMembership($member)->assignRole(Role::create(['name' => 'auditor', 'guard_name' => 'web']));
        [, $task] = $this->projectWithTaskFor($member);
        $member->forceFill(['currant_workspace' => $this->podWorkspace->id])->save();
        $this->failOn($timing, $table);
        $before = $this->snapshot();

        $this->actingAs($this->owner)->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $member->id]))->assertStatus(500);

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame("{$this->owner->id},{$member->id}", $task->fresh()->assign_to);
        $this->assertSame($this->podWorkspace->id, (int) $member->fresh()->currant_workspace);
        $this->assertSame(0, $this->notifications('pod_remove'));
    }

    public function test_successful_removal_cleans_every_dependent_record(): void // 21, 22, 23
    {
        $member = $this->activeMember('member', ['projects_index']);
        $membershipId = $this->workspaceMembership($member)->id;
        $this->workspaceMembership($member)->assignRole(Role::create(['name' => 'auditor', 'guard_name' => 'web']));
        [$project, $task] = $this->projectWithTaskFor($member);
        $other = $this->activeMember('other');
        $similarId = Project::forceCreate(['name' => 'Other', 'workspace' => $this->podWorkspace->id, 'created_by' => $this->owner->id]);
        $lookalike = \App\Models\Task::forceCreate(['title' => 'x', 'project_id' => $similarId->id, 'assign_to' => "{$member->id}1,{$other->id}"]);
        $claim = UserProject::create(['user_id' => $member->id, 'project_id' => $similarId->id, 'is_active' => 0, 'status' => 'pending']);
        DB::table('notifications')->insert(['user_id' => $this->owner->id, 'type' => 'project_request', 'related_id' => $claim->id, 'data' => json_encode($claim), 'is_read' => 0]);
        $member->forceFill(['currant_workspace' => $this->podWorkspace->id])->save();
        $this->notificationLevels = [];

        $this->actingAs($member)->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $member->id]))
            ->assertOk()->assertJsonPath('location', route('change-workspace', ['id' => $this->ownWorkspaceId($member)]));

        $this->assertSame(State::REMOVED, $this->podMembership($member)->state, 'the audit row is kept');
        $this->assertSame(1, $this->podMembership($member)->stateHistory()->where('to_state', State::REMOVED->value)->count());
        $this->assertNull($this->workspaceMembership($member));
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_type', UserWorkspace::class)->where('model_id', $membershipId)->count(), 'no orphaned permissions');
        $this->assertSame(0, DB::table('model_has_roles')->where('model_type', UserWorkspace::class)->where('model_id', $membershipId)->count(), 'no orphaned roles');
        $this->assertSame(0, UserProject::where('user_id', $member->id)->count());
        $this->assertSame((string) $this->owner->id, $task->fresh()->assign_to);
        $this->assertSame("{$member->id}1,{$other->id}", $lookalike->fresh()->assign_to, 'only exact assignee ids are removed');
        $this->assertSame(0, $this->notifications('project_request'));
        $this->assertSame($this->ownWorkspaceId($member), (int) $member->fresh()->currant_workspace);
        $this->assertSame(1, $this->notifications('pod_remove'));
        $this->assertSame([0], $this->notificationLevels);
        $this->assertNotNull($this->podMembership($other), 'other members untouched');
    }

    public function test_removal_of_a_user_without_any_other_workspace_is_safe(): void // 24
    {
        $member = User::createWithAccountRole(['name' => 'Lonely', 'email' => 'lonely@example.test', 'email_verified_at' => now()], UserRole::EMPLOYEE);
        PodUser::forceCreate(['user_id' => $member->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => State::OUTER_CIRCLE->value]);
        UserWorkspace::create(['user_id' => $member->id, 'workspace_id' => $this->podWorkspace->id, 'permission' => 'Member', 'status' => 'active']);
        $member->forceFill(['currant_workspace' => $this->podWorkspace->id])->save();

        $this->actingAs($member)->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $member->id]))
            ->assertOk()->assertJsonPath('location', route('home'));

        $this->assertNull($member->fresh()->currant_workspace);
        $this->assertSame(State::REMOVED, $this->podMembership($member)->state);
    }

    // ================================================================ workspace requests / invitations

    public function test_workspace_join_request_is_resolved_atomically_once(): void
    {
        $company = $this->companyWorkspace();
        $requester = $this->registered('requester', UserRole::EMPLOYEE);
        $this->actingAs($requester)->get(route('company.join', $company->id));
        $membership = UserWorkspace::where('workspace_id', $company->id)->where('user_id', $requester->id)->sole();
        $membership->givePermissionTo('users_index');

        $this->failOn('BEFORE UPDATE', 'notifications');
        $before = $this->snapshot();
        $this->assertFails(fn () => app(WorkspaceMembershipService::class)->declineJoinRequest($membership));
        $this->assertEquals($before, $this->snapshot());
        DB::unprepared('DROP TRIGGER una005_fail');

        $this->assertTrue(app(WorkspaceMembershipService::class)->declineJoinRequest($membership)->applied);
        $this->assertFalse(app(WorkspaceMembershipService::class)->acceptJoinRequest($membership)->applied, 'a racing accept cannot reverse the decline');
        $this->assertSame(['declined', 0], [$membership->fresh()->status, (int) $membership->fresh()->is_active]);
        $this->assertSame([], $membership->fresh()->getPermissionNames()->all());
        $this->assertSame(1, $this->notifications('workspace_request_decline_user'));
        $this->assertSame(0, $this->notifications('workspace_request_accept_user'));
    }

    public function test_workspace_invitations_require_a_pending_invitation_and_skip_pod_workspaces(): void
    {
        $company = $this->companyWorkspace();
        $requester = $this->registered('requester', UserRole::EMPLOYEE);
        $this->actingAs($requester)->get(route('company.join', $company->id)); // workspace_pending

        // A join requester cannot "accept an invitation" to approve themselves.
        $this->assertFalse(app(WorkspaceMembershipService::class)->acceptInvitation($company, $requester)->applied);
        $this->assertSame('workspace_pending', UserWorkspace::where('workspace_id', $company->id)->where('user_id', $requester->id)->value('status'));

        // POD workspace memberships are resolved through the POD flow only.
        $podInvitee = $this->registered('invitee', UserRole::EMPLOYEE);
        app(PodUserService::class)->inviteExistedUser($podInvitee->id, $this->pod->id, []);
        $this->assertFalse(app(WorkspaceMembershipService::class)->acceptInvitation($this->podWorkspace, $podInvitee)->applied);
        $this->assertSame('employee_pending', $this->workspaceMembership($podInvitee)->status);
    }

    public function test_declined_workspace_invitation_is_removed_with_its_permissions_or_not_at_all(): void
    {
        $company = $this->companyWorkspace();
        $invitee = $this->registered('invitee', UserRole::EMPLOYEE);
        $this->actingAs($this->owner)->get(route('user.exists', ['slug' => $company->slug, 'email' => $invitee->email]));
        $membership = UserWorkspace::where('workspace_id', $company->id)->where('user_id', $invitee->id)->sole();

        $this->failOn('BEFORE DELETE', 'user_workspaces');
        $before = $this->snapshot();
        $this->assertFails(fn () => app(WorkspaceMembershipService::class)->declineInvitation($company, $invitee));
        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(1, $this->notifications('workspace_invite'), 'invitation still actionable');
        DB::unprepared('DROP TRIGGER una005_fail');

        $this->assertTrue(app(WorkspaceMembershipService::class)->declineInvitation($company, $invitee)->applied);
        $this->assertNull(UserWorkspace::find($membership->id));
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_type', UserWorkspace::class)->where('model_id', $membership->id)->count());
        $this->assertSame(0, $this->notifications('workspace_invite'));
        $this->assertSame(1, $this->notifications('workspace_request_decline_owner'));
    }

    // ================================================================ project invitations / claims

    public function test_project_invitation_failure_leaves_no_new_workspace_membership_or_mail(): void
    {
        $project = $this->project();
        $invitee = $this->registered('invitee', UserRole::EMPLOYEE);
        $this->failOn('BEFORE INSERT', 'user_projects');
        $before = $this->snapshot();

        $this->assertFails(fn () => app(ProjectMembershipService::class)->invite($invitee, $project, 'Member', $this->owner));

        $this->assertEquals($before, $this->snapshot());
        Mail::assertNotSent(SendWorkspaceInvication::class);
        $this->assertSame(0, $this->notifications('project_assign'));
    }

    public function test_claim_failing_after_insert_is_rolled_back_without_notification(): void // 25
    {
        $project = $this->project();
        $claimant = $this->activeMember('claimant');
        $this->throwAfter('insert', 'user_projects', new \RuntimeException('injected failure after insert'));
        $before = $this->snapshot();

        $this->assertFails(fn () => app(ProjectMembershipService::class)->submitClaim($project, $claimant));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, $this->notifications('project_request'));
    }

    public static function claimDecisions(): array
    {
        return ['accept (26)' => ['acceptClaim', 'project_accepted'], 'decline (27)' => ['declineClaim', 'project_decline']];
    }

    #[DataProvider('claimDecisions')]
    public function test_failed_claim_decision_restores_pending_state_and_notification(string $method, string $notice): void // 26, 27, 28
    {
        $project = $this->project();
        $claimant = $this->activeMember('claimant');
        app(ProjectMembershipService::class)->submitClaim($project, $claimant);
        $claim = UserProject::where('user_id', $claimant->id)->sole();
        $this->failOn('BEFORE DELETE', 'notifications');
        $before = $this->snapshot();

        $this->assertFails(fn () => app(ProjectMembershipService::class)->{$method}($claim));

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(['pending', 0, null], [$claim->fresh()->status, (int) $claim->fresh()->is_active, $claim->fresh()->permission]);
        $this->assertSame(1, $this->notifications('project_request'), 'pending-request card rolls back with the claim');
        $this->assertSame(0, $this->notifications($notice));
    }

    public function test_claim_decisions_apply_once_and_notify_once(): void // 29, 30
    {
        $project = $this->project();
        $claimant = $this->activeMember('claimant');
        $this->actingAs($claimant)->get(route('pods.projects.join', ['project' => $project->id]))->assertSessionHas('success');
        $this->actingAs($claimant)->get(route('pods.projects.join', ['project' => $project->id]))->assertSessionHas('error'); // UNA-004 repeat
        $claim = UserProject::where('user_id', $claimant->id)->sole();

        $this->actingAs($this->owner)->get(route('pods.projects.join.accept', ['record' => $claim->id]))->assertSessionHas('success');
        $this->actingAs($this->owner)->get(route('pods.projects.join.accept', ['record' => $claim->id]))->assertSessionHas('error');
        $this->actingAs($this->owner)->get(route('pods.projects.join.decline', ['record' => $claim->id]))->assertSessionHas('error');

        $fresh = $claim->fresh();
        $this->assertSame(['active', 1], [$fresh->status, (int) $fresh->is_active]);
        $this->assertNotNull(json_decode((string) $fresh->permission, true), 'full project permission JSON applied');
        $this->assertSame(0, $this->notifications('project_request'));
        $this->assertSame(1, $this->notifications('project_accepted'));
        $this->assertSame(0, $this->notifications('project_decline'));
    }

    // ================================================================ after-commit semantics

    public function test_effects_run_once_after_a_deadlock_retry(): void
    {
        $requester = $this->registered('requester', UserRole::EMPLOYEE);
        $this->throwAfter('insert', 'pod_users', new QueryException('sqlite', 'insert into pod_users', [], new \PDOException('Deadlock found when trying to get lock; try restarting transaction')));

        $result = app(PodUserService::class)->requestJoinPod($requester->id, $this->pod->id);

        $this->assertTrue($result->applied);
        $this->assertSame(1, PodUser::where('user_id', $requester->id)->count(), 'the retried attempt committed once');
        $this->assertSame(1, $this->notifications('pod_request'), 'effects ran once, not once per attempt');
    }

    public function test_effects_wait_for_an_enclosing_transaction_and_vanish_on_its_rollback(): void
    {
        $committed = $this->registered('committed', UserRole::EMPLOYEE);
        DB::transaction(function () use ($committed) {
            app(PodUserService::class)->requestJoinPod($committed->id, $this->pod->id);
            $this->assertSame(0, $this->notifications('pod_request'), 'not before the outer commit');
        });
        $this->assertSame(1, $this->notifications('pod_request'));

        $rolledBack = $this->registered('rolled-back', UserRole::EMPLOYEE);
        DB::beginTransaction();
        app(PodUserService::class)->requestJoinPod($rolledBack->id, $this->pod->id);
        DB::rollBack();
        $this->assertSame(1, $this->notifications('pod_request'), 'no notification for the rolled-back request');
        $this->assertNull($this->podMembership($rolledBack));
    }

    public function test_external_delivery_failure_after_commit_does_not_undo_the_membership(): void
    {
        $requester = $this->registered('requester', UserRole::EMPLOYEE);
        Notification::creating(fn () => throw new \RuntimeException('push provider down'));

        $result = app(PodUserService::class)->requestJoinPod($requester->id, $this->pod->id);

        $this->assertTrue($result->applied);
        $this->assertArrayHasKey('notify_owner', $result->failedEffects);
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $this->podMembership($requester)->state);
    }

    // ================================================================ helpers

    private function registered(string $name, UserRole $role): User
    {
        $user = User::createWithAccountRole(['name' => $name, 'email' => "{$name}@example.test", 'plan' => 1, 'email_verified_at' => now()], $role);
        $workspace = Workspace::create(['name' => "{$name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner'])->assignAllPermissionsToCreator();
        $user->forceFill(['currant_workspace' => $workspace->id])->save();
        if ($role === UserRole::EMPLOYEE) {
            $this->completeEmployeeProfile($user); // unrelated to UNA-007 profile eligibility
        }

        return $user->fresh();
    }

    private function companyWorkspace(): Workspace
    {
        return Workspace::findOrFail($this->ownWorkspaceId($this->owner));
    }

    private function ownWorkspaceId(User $user): int
    {
        return (int) Workspace::where('created_by', $user->id)->where('ownerable_type', User::class)->value('id');
    }

    private function applicant(string $name): User
    {
        $user = $this->registered($name, UserRole::EMPLOYEE);
        app(PodUserService::class)->requestJoinPod($user->id, $this->pod->id);
        $this->notificationLevels = [];

        return $user;
    }

    /** Active member; with projects_index they are explicitly Inner Circle (state and access agree). */
    private function activeMember(string $name, array $extraPermissions = []): User
    {
        $user = $this->registered($name, UserRole::EMPLOYEE);
        $state = in_array('projects_index', $extraPermissions, true) ? State::INNER_CIRCLE : State::OUTER_CIRCLE;
        PodUser::forceCreate(['user_id' => $user->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => $state->value]);
        UserWorkspace::create(['user_id' => $user->id, 'workspace_id' => $this->podWorkspace->id, 'permission' => 'Member', 'status' => 'active'])
            ->givePermissionTo(array_merge(['users_index', 'news_index'], $extraPermissions));

        return $user;
    }

    private function project(): Project
    {
        return Project::forceCreate(['name' => 'Project', 'workspace' => $this->podWorkspace->id, 'created_by' => $this->owner->id, 'is_active' => 1]);
    }

    /** @return array{0: Project, 1: \App\Models\Task} */
    private function projectWithTaskFor(User $member): array
    {
        $project = $this->project();
        UserProject::create(['user_id' => $member->id, 'project_id' => $project->id, 'is_active' => 1, 'status' => 'active']);
        $task = \App\Models\Task::forceCreate(['title' => 'Task', 'project_id' => $project->id, 'assign_to' => "{$this->owner->id},{$member->id}"]);

        return [$project, $task];
    }

    private function podMembership(User $user): ?PodUser
    {
        return PodUser::where('pod_id', $this->pod->id)->where('user_id', $user->id)->first();
    }

    private function workspaceMembership(User $user): ?UserWorkspace
    {
        return UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $user->id)->first();
    }

    private function notifications(string $type): int
    {
        return Notification::where('type', $type)->count();
    }

    /** Make the next matching write on $table fail inside the database (rolls the statement back). */
    private function failOn(string $timing, string $table): void
    {
        DB::unprepared("CREATE TRIGGER una005_fail {$timing} ON {$table} BEGIN SELECT RAISE(ABORT, 'injected failure on {$table}'); END;");
    }

    /** Throw once, right after the first $verb statement on $table has executed. */
    private function throwAfter(string $verb, string $table, \Throwable $error): void
    {
        $thrown = false;
        DB::listen(function (QueryExecuted $query) use (&$thrown, $verb, $table, $error) {
            if (!$thrown && str_starts_with(strtolower($query->sql), $verb) && str_contains($query->sql, '"' . $table . '"')) {
                $thrown = true;
                throw $error;
            }
        });
    }

    private function assertFails(callable $operation, ?string $message = null): void
    {
        try {
            $operation();
        } catch (\Throwable $e) {
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }
            $this->assertSame(0, DB::transactionLevel(), 'the transaction was closed');

            return;
        }
        $this->fail('expected the operation to fail');
    }

    /** Every table an operation could touch, compared row by row. */
    private function snapshot(): array
    {
        $tables = ['users', 'pod_users', 'pod_user_state_histories', 'user_workspaces', 'user_projects', 'tasks', 'model_has_permissions', 'model_has_roles', 'notifications', 'roles'];

        return collect($tables)->mapWithKeys(fn ($table) => [
            $table => DB::table($table)->get()->map(fn ($row) => (array) $row)->sortBy(fn ($row) => json_encode($row))->values()->all(),
        ])->all();
    }
}

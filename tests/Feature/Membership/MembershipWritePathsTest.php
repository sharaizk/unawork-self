<?php

namespace Tests\Feature\Membership;

use App\Enums\PodUser\State;
use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Http\Controllers\ProjectController;
use App\Jobs\SendEmail;
use App\Models\Mail\SendWorkspaceInvication;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\PodService;
use App\Services\PodUserService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-004: membership writes are atomic and constraint-backed. Repeated and racing requests
 * leave one row, run side effects once, never downgrade/reset state and never surface a
 * duplicate-key error. The race is reproduced deterministically by committing the competing
 * row right after the application's lookup and before its insert (a query listener).
 */
class MembershipWritePathsTest extends TestCase
{
    use UsesInMemorySqlite;

    private User $owner;
    private Pod $pod;
    private Workspace $podWorkspace;

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
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Mail::fake();
        Bus::fake();

        $this->owner = $this->user('owner', UserRole::COMPANY);
        $this->completeBusinessProfile($this->owner); // UNA-010: publishing a POD needs a complete company profile
        $this->pod = app(PodService::class)->createPod(['title' => 'Pod', 'description' => 'd', 'created_by' => $this->owner->id]);
        $this->podWorkspace = $this->pod->workspace;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // -- PODs ---------------------------------------------------------------------------------

    public function test_repeated_pod_invitation_leaves_one_membership_and_one_set_of_side_effects(): void
    {
        $invitee = $this->user('invitee', UserRole::EMPLOYEE);
        $service = app(PodUserService::class);

        $service->inviteExistedUser($invitee->id, $this->pod->id, []);
        $this->assertBusinessError('This user is already a member of this pod.', fn () => $service->inviteExistedUser($invitee->id, $this->pod->id, []));

        $this->assertSame(1, PodUser::where('pod_id', $this->pod->id)->where('user_id', $invitee->id)->count());
        $this->assertSame(1, UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $invitee->id)->count());
        $this->assertSame(1, $this->notifications('pod_invite', $invitee->id));
        Bus::assertDispatchedTimes(SendEmail::class, 1);
    }

    public function test_repeated_pod_join_request_leaves_one_row_and_one_notification(): void
    {
        $requester = $this->user('requester', UserRole::EMPLOYEE);
        $service = app(PodUserService::class);

        $service->requestJoinPod($requester->id, $this->pod->id);
        $this->assertBusinessError('You have already submitted a join request for this pod.', fn () => $service->requestJoinPod($requester->id, $this->pod->id));

        $this->assertSame(1, PodUser::where('pod_id', $this->pod->id)->where('user_id', $requester->id)->count());
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $requester->id)->value('state'));
        $this->assertSame(1, $this->notifications('pod_request', $this->owner->id));
    }

    public function test_racing_pod_join_request_gets_the_business_outcome_without_side_effects(): void
    {
        $requester = $this->user('requester', UserRole::EMPLOYEE);
        $this->competingInsertAfterLookup('pod_users', [
            'user_id' => $requester->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => State::PENDING_BUSINESS_APPROVAL->value,
        ]);

        $this->assertBusinessError('You have already submitted a join request for this pod.', fn () => app(PodUserService::class)->requestJoinPod($requester->id, $this->pod->id));

        $this->assertSame(1, PodUser::where('pod_id', $this->pod->id)->where('user_id', $requester->id)->count());
        $this->assertSame(0, $this->notifications('pod_request', $this->owner->id));
        $this->assertSame(0, UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $requester->id)->count());
    }

    public function test_declined_pod_membership_is_not_reactivated_and_owner_is_not_downgraded(): void
    {
        $declined = $this->user('declined', UserRole::EMPLOYEE);
        PodUser::forceCreate(['user_id' => $declined->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => State::REJECTED->value, 'reason' => 'No']);
        $service = app(PodUserService::class);

        // UNA-006 re-entry rule: a rejected member may re-apply. The SAME row moves to
        // pending_business_approval (no duplicate, never straight back to active membership).
        $service->requestJoinPod($declined->id, $this->pod->id);
        $this->assertSame(1, PodUser::where('pod_id', $this->pod->id)->where('user_id', $declined->id)->count());
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $declined->id)->value('state'));
        $this->assertBusinessError('You have already submitted a join request for this pod.', fn () => $service->requestJoinPod($declined->id, $this->pod->id));

        $this->assertBusinessError('This user is already a member of this pod.', fn () => $service->inviteExistedUser($this->owner->id, $this->pod->id, []));
        $ownerRow = PodUser::where('pod_id', $this->pod->id)->where('user_id', $this->owner->id)->sole();
        $this->assertSame(['Owner', State::INNER_CIRCLE], [$ownerRow->permission, $ownerRow->state]);
    }

    public function test_accepting_twice_or_concurrently_changes_and_notifies_once(): void
    {
        $applicant = $this->user('applicant', UserRole::EMPLOYEE);
        app(PodUserService::class)->requestJoinPod($applicant->id, $this->pod->id);

        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))->assertOk();
        // UNA-001: once handled, the request is no longer acceptable.
        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))->assertForbidden();

        // A request that passed authorization before the first one wrote: no change, no notification, no 500.
        Gate::before(fn () => true);
        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))->assertStatus(409);
        $this->actingAs($this->owner)->postJson(route('pods.decline', ['pod' => $this->pod, 'user' => $applicant]))->assertStatus(409);

        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $applicant->id)->value('state'));
        $this->assertSame(1, $this->notifications('pod_request_accept_user', $applicant->id));
        $this->assertSame(0, $this->notifications('pod_request_decline_user', $applicant->id));
    }

    // -- Workspaces ---------------------------------------------------------------------------

    public function test_repeated_workspace_invitation_leaves_one_row_and_does_not_reset_state(): void
    {
        $workspace = $this->ownWorkspace($this->owner);
        $invitee = $this->user('invitee', UserRole::EMPLOYEE);

        $first = $this->actingAs($this->owner)->get(route('user.exists', ['slug' => $workspace->slug, 'email' => $invitee->email]));
        $this->assertSame(200, json_decode($first->getContent(), true)['code']);
        UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $invitee->id)->update(['status' => 'active']);

        $second = $this->actingAs($this->owner)->get(route('user.exists', ['slug' => $workspace->slug, 'email' => $invitee->email]));
        $this->assertSame(202, json_decode($second->getContent(), true)['code']);

        $membership = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $invitee->id)->sole();
        $this->assertSame('active', $membership->status, 'an active member is not reset to pending');
        $this->assertSame(1, $this->notifications('workspace_invite', $invitee->id));
    }

    public function test_repeated_workspace_join_request_leaves_one_row_and_one_notification(): void
    {
        $workspace = $this->ownWorkspace($this->owner);
        $requester = $this->user('requester', UserRole::EMPLOYEE);

        $this->actingAs($requester)->get(route('company.join', $workspace->id))->assertRedirect(route('home'));
        $this->actingAs($requester)->get(route('company.join', $workspace->id))->assertNotFound();

        $this->assertSame(1, UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $requester->id)->count());
        $this->assertSame(1, $this->notifications('workspace_join', $this->owner->id));
    }

    // -- Projects -----------------------------------------------------------------------------

    public function test_repeated_project_invitation_leaves_one_row_each_and_never_downgrades_the_owner(): void
    {
        $project = $this->project();
        $invitee = $this->user('invitee', UserRole::EMPLOYEE);
        $this->actingAs($this->owner);
        $controller = app(ProjectController::class);

        $controller->inviteUser($invitee, $project, 'Member');
        $controller->inviteUser($invitee, $project, 'Member');
        $controller->inviteUser($this->owner, $project, 'Member'); // owner already in the workspace

        $this->assertSame(1, UserProject::where('project_id', $project->id)->where('user_id', $invitee->id)->count());
        $this->assertSame(1, UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $invitee->id)->count());
        $this->assertSame(1, $this->notifications('project_assign', $invitee->id));
        Mail::assertSent(SendWorkspaceInvication::class, 1);
        $this->assertSame('Owner', UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $this->owner->id)->value('permission'));
    }

    public function test_repeated_and_racing_project_claims_leave_one_row_and_one_notification(): void
    {
        $project = $this->project();
        $claimant = $this->activePodMember('claimant');
        $racer = $this->activePodMember('racer');

        $this->actingAs($claimant)->get(route('pods.projects.join', ['project' => $project->id]))->assertSessionHas('success');
        $this->actingAs($claimant)->get(route('pods.projects.join', ['project' => $project->id]))->assertSessionHas('error', __('You already send Join request!'));

        $this->competingInsertAfterLookup('user_projects', ['user_id' => $racer->id, 'project_id' => $project->id, 'is_active' => 0, 'status' => 'pending']);
        $this->actingAs($racer)->get(route('pods.projects.join', ['project' => $project->id]))
            ->assertRedirect()->assertSessionHas('error', __('You already send Join request!'));

        $this->assertSame(1, UserProject::where('project_id', $project->id)->where('user_id', $claimant->id)->count());
        $this->assertSame(1, UserProject::where('project_id', $project->id)->where('user_id', $racer->id)->count());
        $this->assertSame(1, $this->notifications('project_request', $this->owner->id), 'only the claim this request created notifies');
    }

    public function test_claims_are_resolved_once_and_declined_claims_are_not_reactivated(): void
    {
        $project = $this->project();
        $claimant = $this->activePodMember('claimant');
        $this->actingAs($claimant)->get(route('pods.projects.join', ['project' => $project->id]));
        $claim = UserProject::where('user_id', $claimant->id)->sole();

        $this->actingAs($this->owner)->get(route('pods.projects.join.accept', ['record' => $claim->id]))->assertSessionHas('success');
        $this->actingAs($this->owner)->get(route('pods.projects.join.decline', ['record' => $claim->id]))
            ->assertSessionHas('error', __('This membership request has already been handled.'));
        $this->assertSame(['active', 1], [$claim->fresh()->status, (int) $claim->fresh()->is_active], 'an accepted member is never downgraded');
        $this->assertSame(1, $this->notifications('project_accepted', $claimant->id));

        $other = $this->activePodMember('other');
        UserProject::create(['user_id' => $other->id, 'project_id' => $project->id, 'is_active' => 0, 'status' => 'declined']);
        $this->actingAs($other)->get(route('pods.projects.join', ['project' => $project->id]))->assertSessionHas('error');
        $this->assertSame('declined', UserProject::where('user_id', $other->id)->value('status'));
    }

    // -- The atomic writer ----------------------------------------------------------------------

    public function test_atomic_writer_returns_existing_rows_and_never_swallows_other_errors(): void
    {
        $user = $this->user('member', UserRole::EMPLOYEE);
        $created = PodUser::findOrCreateMembership($user->id, $this->pod->id, ['permission' => 'Member', 'state' => State::PENDING_BUSINESS_APPROVAL->value]);
        $this->assertTrue($created->wasRecentlyCreated);

        // The race path: insert first, collide on the unique index, return the winner untouched.
        $existing = PodUser::createMembershipOrReturnExisting($user->id, $this->pod->id, ['permission' => 'Owner', 'state' => State::INNER_CIRCLE->value]);
        $this->assertFalse($existing->wasRecentlyCreated);
        $this->assertSame($created->id, $existing->id);
        $this->assertSame(['Member', State::PENDING_BUSINESS_APPROVAL], [$existing->permission, $existing->state]);

        // Any other database error still surfaces (here: NOT NULL columns left out).
        try {
            PodUser::findOrCreateMembership($this->user('broken', UserRole::EMPLOYEE)->id, $this->pod->id);
            $this->fail('expected a database error');
        } catch (QueryException $e) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $e);
        }
    }

    public function test_pod_owner_workspace_membership_still_gets_every_workspace_permission(): void
    {
        $membership = UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $this->owner->id)->sole();

        $this->assertSame('Owner', $membership->permission);
        $this->assertEqualsCanonicalizing(WorkspacePermission::values(), $membership->getPermissionNames()->all()); // UNA-003
        $this->assertTrue($this->owner->fresh()->isCompany()); // UNA-002
    }

    // -- Helpers --------------------------------------------------------------------------------

    private function user(string $name, UserRole $role): User
    {
        $user = User::createWithAccountRole(['name' => $name, 'email' => $name . '-' . uniqid() . '@example.test', 'plan' => 1, 'email_verified_at' => now()], $role);
        if ($role === UserRole::EMPLOYEE) {
            $this->completeEmployeeProfile($user); // unrelated to UNA-007 profile eligibility
        }

        return $user;
    }

    private function ownWorkspace(User $owner): Workspace
    {
        $workspace = Workspace::create(['name' => 'Company WS', 'created_by' => $owner->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($owner)->save();
        UserWorkspace::findOrCreateMembership($owner->id, $workspace->id, ['permission' => 'Owner'])->assignAllPermissionsToCreator();
        $owner->forceFill(['currant_workspace' => $workspace->id])->save();

        return $workspace->fresh();
    }

    private function project(): Project
    {
        return Project::forceCreate(['name' => 'Project', 'workspace' => $this->podWorkspace->id, 'created_by' => $this->owner->id, 'is_active' => 1]);
    }

    private function activePodMember(string $name): User
    {
        $user = $this->user($name, UserRole::EMPLOYEE);
        PodUser::forceCreate(['user_id' => $user->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => State::OUTER_CIRCLE->value]);
        UserWorkspace::create(['user_id' => $user->id, 'workspace_id' => $this->podWorkspace->id, 'permission' => 'Member', 'status' => 'active']);

        return $user;
    }

    /**
     * Simulate a concurrent request: right after this request's lookup on $table found nothing,
     * another "request" commits the same pair, so this request's insert collides on the index.
     */
    private function competingInsertAfterLookup(string $table, array $row): void
    {
        $done = false;
        DB::listen(function (QueryExecuted $query) use (&$done, $table, $row) {
            if (!$done && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '"' . $table . '"')) {
                $done = true;
                DB::table($table)->insert($row);
            }
        });
    }

    private function assertBusinessError(string $message, callable $call): void
    {
        try {
            $call();
            $this->fail("expected: {$message}");
        } catch (\Exception $e) {
            $this->assertNotInstanceOf(QueryException::class, $e);
            $this->assertSame($message, $e->getMessage());
        }
    }

    private function notifications(string $type, int $userId): int
    {
        return DB::table('notifications')->where('type', $type)->where('user_id', $userId)->count();
    }
}

<?php

namespace Tests\Feature\Membership;

use App\Enums\PodUser\State;
use App\Enums\User\UserRole;
use App\Jobs\SendEmail;
use App\Models\Notification;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\PodUserStateHistory;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\Membership\InvalidStateTransition;
use App\Services\Membership\PodStateTransitionService;
use App\Services\PodService;
use App\Services\PodUserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-006: explicit POD membership states - transition graph, reasons, owner protection,
 * append-only history, state-driven access, rollback and the rewired workflows. In-memory SQLite only.
 */
class PodStateTransitionTest extends TestCase
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

        $this->owner = $this->user('owner', UserRole::COMPANY);
        $this->pod = app(PodService::class)->createPod(['title' => 'Pod', 'description' => 'd', 'created_by' => $this->owner->id]);
        $this->podWorkspace = $this->pod->workspace;
    }

    // ================================================================ transition matrix

    public static function allowedTransitions(): array
    {
        $cases = [];
        foreach (State::cases() as $from) {
            foreach ($from->transitions() as $to) {
                $cases["{$from->value} -> {$to->value}"] = [$from, $to];
            }
        }

        return $cases;
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_succeed_and_record_history(State $from, State $to): void // 15, 20
    {
        $membership = $this->membership('member', $from);

        $result = $this->transitions()->transition($membership, $to, $this->owner, 'A reason');

        $this->assertTrue($result->applied);
        $this->assertSame($to, $membership->fresh()->state);
        $history = PodUserStateHistory::where('pod_user_id', $membership->id)->latest('id')->first();
        $this->assertSame([$membership->id, $from, $to, $this->owner->id, 'A reason'], [$history->pod_user_id, $history->from_state, $history->to_state, $history->actor_id, $history->reason]);
        $this->assertNotNull($history->created_at);
    }

    public static function forbiddenTransitions(): array
    {
        $cases = [];
        foreach (State::cases() as $from) {
            foreach (State::cases() as $to) {
                if ($from !== $to && !$from->canTransitionTo($to)) {
                    $cases["{$from->value} -> {$to->value}"] = [$from, $to];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transitions_throw_and_change_nothing(State $from, State $to): void // 16
    {
        $membership = $this->membership('member', $from);
        $history = PodUserStateHistory::count();

        $this->expectException(InvalidStateTransition::class);
        try {
            $this->transitions()->transition($membership, $to, $this->owner, 'A reason');
        } finally {
            $this->assertSame($from, $membership->fresh()->state);
            $this->assertSame($history, PodUserStateHistory::count());
        }
    }

    public function test_same_state_is_a_no_op_without_history(): void // 17
    {
        $membership = $this->membership('member', State::OUTER_CIRCLE);
        $history = PodUserStateHistory::count();

        $result = $this->transitions()->transition($membership, State::OUTER_CIRCLE, $this->owner);

        $this->assertFalse($result->applied);
        $this->assertSame('no_change', $result->reason);
        $this->assertSame($history, PodUserStateHistory::count());
    }

    public function test_reasons_are_required_and_normalized(): void // 18
    {
        foreach ([[State::OUTER_CIRCLE, State::SUSPENDED], [State::OUTER_CIRCLE, State::REMOVED], [State::PENDING_BUSINESS_APPROVAL, State::REJECTED], [State::INNER_CIRCLE, State::OUTER_CIRCLE], [State::SUSPENDED, State::OUTER_CIRCLE]] as $i => [$from, $to]) {
            $membership = $this->membership("m{$i}", $from);
            foreach ([null, '', '   '] as $blank) {
                try {
                    $this->transitions()->transition($membership, $to, $this->owner, $blank);
                    $this->fail("{$from->value} -> {$to->value} accepted a blank reason");
                } catch (InvalidStateTransition) {
                    $this->assertSame($from, $membership->fresh()->state);
                }
            }
            $this->transitions()->transition($membership, $to, $this->owner, "  trimmed  ");
            $this->assertSame('trimmed', $membership->stateHistory()->get()->last()->reason);
        }
    }

    public function test_the_owner_membership_cannot_change(): void // 19
    {
        $ownerRow = PodUser::where('pod_id', $this->pod->id)->where('user_id', $this->owner->id)->sole();
        $this->assertSame(State::INNER_CIRCLE, $ownerRow->state);

        foreach ([State::OUTER_CIRCLE, State::REJECTED, State::SUSPENDED, State::REMOVED] as $target) {
            try {
                $this->transitions()->transition($ownerRow, $target, $this->owner, 'x');
                $this->fail("owner moved to {$target->value}");
            } catch (InvalidStateTransition) {
                $this->assertSame(State::INNER_CIRCLE, $ownerRow->fresh()->state);
            }
        }
        $this->actingAs($this->owner)->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $this->owner->id]))->assertForbidden();
    }

    public function test_racing_transitions_from_the_same_state_apply_once(): void // 21
    {
        $membership = $this->membership('member', State::PENDING_BUSINESS_APPROVAL);

        $first = $this->transitions()->transition($membership, State::OUTER_CIRCLE, $this->owner);
        // A decision that read the same starting state before the first one committed.
        $second = DB::transaction(fn () => $this->transitions()->apply($membership, State::REJECTED, $this->owner, 'late', [State::PENDING_BUSINESS_APPROVAL]));

        $this->assertTrue($first->applied);
        $this->assertSame('already_handled', $second->reason);
        $this->assertSame(State::OUTER_CIRCLE, $membership->fresh()->state);
        $this->assertSame(1, $membership->stateHistory()->where('from_state', State::PENDING_BUSINESS_APPROVAL->value)->count());
    }

    // ================================================================ access synchronization

    public function test_access_follows_the_state(): void // 22-26
    {
        foreach ([State::MATCHED, State::PENDING_BUSINESS_APPROVAL] as $i => $state) {
            $applicant = $this->membership("applicant{$i}", $state);
            $this->assertNoAccess($applicant, 'employee_pending');
        }

        $outer = $this->membership('outer', State::PENDING_BUSINESS_APPROVAL);
        $this->workspaceOf($outer)->givePermissionTo('users_edit');
        $this->transitions()->transition($outer, State::OUTER_CIRCLE, $this->owner);
        // Nothing granted while without access survives approval: exactly the baseline.
        $this->assertPermissions($outer, ['users_index', 'news_index']);

        $this->workspaceOf($outer)->givePermissionTo(['users_edit', 'projects_edit']);
        foreach ([State::SCREENING_IN_PROGRESS, State::SCREENING_COMPLETED, State::PENDING_INNER_APPROVAL] as $screening) {
            $this->transitions()->transition($outer, $screening, $this->owner);
            // Baseline + independent management grants; never projects_edit without project visibility.
            $this->assertPermissions($outer, ['users_index', 'news_index', 'users_edit'], $screening->value);
            $this->assertSame('active', $this->workspaceOf($outer)->status);
        }

        $this->transitions()->transition($outer, State::INNER_CIRCLE, $this->owner);
        $this->assertPermissions($outer, ['users_index', 'news_index', 'users_edit', 'projects_index']); // no projects_edit granted
    }

    public function test_demotion_suspension_and_rejection_revoke_access_atomically(): void // 27-29
    {
        $member = $this->membership('member', State::INNER_CIRCLE, ['projects_edit']);
        $this->transitions()->transition($member, State::OUTER_CIRCLE, $this->owner, 'demoted');
        $this->assertPermissions($member, ['users_index', 'news_index']);
        $this->assertFalse(Gate::forUser($member->user)->allows('viewInner', $this->pod));

        $this->transitions()->transition($member, State::SUSPENDED, $this->owner, 'paused');
        $this->assertNoAccess($member, 'declined');

        $screened = $this->membership('screened', State::SCREENING_IN_PROGRESS);
        $this->transitions()->transition($screened, State::REJECTED, $this->owner, 'no');
        $this->assertNoAccess($screened, 'declined');
        $this->assertSame('no', $screened->fresh()->reason);
    }

    public function test_removal_keeps_the_audit_row_and_cleans_access(): void // 30, 46
    {
        $member = $this->membership('member', State::INNER_CIRCLE);
        $project = Project::forceCreate(['name' => 'P', 'workspace' => $this->podWorkspace->id, 'created_by' => $this->owner->id]);
        UserProject::create(['user_id' => $member->user_id, 'project_id' => $project->id, 'is_active' => 1, 'status' => 'active']);
        $task = \App\Models\Task::forceCreate(['title' => 't', 'project_id' => $project->id, 'assign_to' => "{$this->owner->id},{$member->user_id}"]);
        $uwId = $this->workspaceOf($member)->id;

        $this->actingAs($member->user)->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $member->user_id]))->assertOk();

        $this->assertSame(State::REMOVED, $member->fresh()->state);
        $this->assertSame('Left the POD', $member->stateHistory()->get()->last()->reason);
        $this->assertSame($member->user_id, $member->stateHistory()->get()->last()->actor_id);
        $this->assertNull(UserWorkspace::find($uwId));
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_type', UserWorkspace::class)->where('model_id', $uwId)->count());
        $this->assertSame(0, UserProject::where('user_id', $member->user_id)->count());
        $this->assertSame((string) $this->owner->id, $task->fresh()->assign_to);
        $this->assertFalse($this->pod->users()->whereKey($member->user_id)->exists(), 'removed memberships are not current');
        $this->assertTrue($this->pod->memberships()->whereKey($member->id)->exists(), 'but remain queryable');
    }

    public function test_permissions_never_decide_the_state(): void // 31, 32, 33
    {
        $outer = $this->membership('outer', State::OUTER_CIRCLE);
        $this->workspaceOf($outer)->givePermissionTo('projects_index'); // inconsistent on purpose
        $inner = $this->membership('inner', State::INNER_CIRCLE);
        $this->workspaceOf($inner)->revokePermissionTo('projects_index');

        $this->assertSame(State::OUTER_CIRCLE, $outer->fresh()->state);
        $this->assertSame(State::OUTER_CIRCLE->label(), $outer->fresh()->state->label());
        $this->assertSame([$inner->id], PodUser::innerCircle()->where('pod_id', $this->pod->id)->where('permission', 'Member')->pluck('id')->all());
        $this->assertContains($outer->id, PodUser::outerCircle()->pluck('id')->all());

        // Inner Circle pages need BOTH the explicit state and the permission.
        $this->assertFalse(Gate::forUser($outer->user)->allows('viewInner', $this->pod), 'permission without state');
        $this->assertFalse(Gate::forUser($inner->user)->allows('viewInner', $this->pod), 'state without permission');
        $this->assertTrue(Gate::forUser($this->owner)->allows('viewInner', $this->pod));

        // The member list's badge reads the pivot state, not permissions.
        $pivot = $this->pod->users()->whereKey($outer->user_id)->first()->pivot;
        $this->assertSame(State::OUTER_CIRCLE->label(), State::from($pivot->state)->label());
    }

    // ================================================================ transactions / history

    public function test_failures_roll_back_state_access_and_history(): void // 34, 35, 37
    {
        $member = $this->membership('member', State::OUTER_CIRCLE);
        foreach ([['BEFORE INSERT', 'model_has_permissions'], ['BEFORE INSERT', 'pod_user_state_histories']] as [$timing, $table]) {
            DB::unprepared("CREATE TRIGGER una006_fail {$timing} ON {$table} BEGIN SELECT RAISE(ABORT, 'injected'); END;");
            $before = $this->snapshot();
            try {
                $this->transitions()->transition($member, State::INNER_CIRCLE, $this->owner);
                $this->fail("{$table} failure did not propagate");
            } catch (\Illuminate\Database\QueryException) {
                $this->assertEquals($before, $this->snapshot(), "{$table}: nothing changed");
            }
            DB::unprepared('DROP TRIGGER una006_fail');
        }
        $this->assertSame(0, Notification::where('type', 'pod_inner_circle_invite')->count(), 'no effect for a rolled-back transition');
    }

    public function test_failed_removal_restores_everything(): void // 36
    {
        $member = $this->membership('member', State::OUTER_CIRCLE);
        $project = Project::forceCreate(['name' => 'P', 'workspace' => $this->podWorkspace->id, 'created_by' => $this->owner->id]);
        UserProject::create(['user_id' => $member->user_id, 'project_id' => $project->id, 'is_active' => 1, 'status' => 'active']);
        \App\Models\Task::forceCreate(['title' => 't', 'project_id' => $project->id, 'assign_to' => (string) $member->user_id]);
        DB::unprepared("CREATE TRIGGER una006_fail BEFORE INSERT ON pod_user_state_histories BEGIN SELECT RAISE(ABORT, 'injected'); END;");
        $before = $this->snapshot();

        $this->actingAs($this->owner)->deleteJson(route('pods.remove', ['podId' => $this->pod->id, 'userId' => $member->user_id]))->assertStatus(500);

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, Notification::where('type', 'pod_remove')->count());
    }

    public function test_effects_run_once_after_commit(): void // 38
    {
        $member = $this->membership('member', State::OUTER_CIRCLE);
        $levels = [];
        Notification::creating(function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        $this->transitions()->transition($member, State::INNER_CIRCLE, $this->owner);
        $this->transitions()->transition($member, State::INNER_CIRCLE, $this->owner); // no-op

        $this->assertSame(1, Notification::where('type', 'pod_inner_circle_invite')->count());
        $this->assertSame([0], $levels);
    }

    public function test_history_is_append_only(): void
    {
        $history = PodUserStateHistory::firstOrFail();

        $this->expectException(\LogicException::class);
        $history->forceFill(['reason' => 'rewritten'])->save();
    }

    // ================================================================ rewired workflows

    public function test_invitation_and_application_workflows(): void // 39-45, 47
    {
        $service = app(PodUserService::class);
        $invitee = $this->user('invitee', UserRole::EMPLOYEE);
        $applicant = $this->user('applicant', UserRole::EMPLOYEE);

        $service->inviteExistedUser($invitee->id, $this->pod->id, [], $this->owner);
        $this->assertState($invitee, State::MATCHED, $this->owner);                                  // 39
        $this->actingAs($invitee)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $invitee]))->assertOk();
        $this->assertState($invitee, State::OUTER_CIRCLE, $invitee);                                 // 40

        $service->requestJoinPod($applicant->id, $this->pod->id);
        $this->assertState($applicant, State::PENDING_BUSINESS_APPROVAL, $applicant);                // 41
        $this->actingAs($this->owner)->postJson(route('pods.decline', ['pod' => $this->pod, 'user' => $applicant]))->assertOk();
        $this->assertState($applicant, State::REJECTED, $this->owner);                               // 43

        $service->requestJoinPod($applicant->id, $this->pod->id);                                    // 47 re-entry, same row
        $this->assertSame(1, PodUser::where('pod_id', $this->pod->id)->where('user_id', $applicant->id)->count());
        $this->assertState($applicant, State::PENDING_BUSINESS_APPROVAL, $applicant);
        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))->assertOk();
        $this->assertState($applicant, State::OUTER_CIRCLE, $this->owner);                           // 42

        // 44 / 45: explicit circle target from the permissions form.
        $this->actingAs($this->owner)->put(route('pod.users.updatePermissions', ['pod' => $this->pod, 'user' => $applicant]), [
            'permissions' => ['users_index', 'news_index', 'users_edit'], 'circle_state' => State::INNER_CIRCLE->value,
        ])->assertSessionHas('success');
        $this->assertState($applicant, State::INNER_CIRCLE, $this->owner);
        $this->assertPermissions($this->podUser($applicant), ['users_index', 'news_index', 'users_edit', 'projects_index']);

        $this->actingAs($this->owner)->put(route('pod.users.updatePermissions', ['pod' => $this->pod, 'user' => $applicant]), [
            'permissions' => ['users_edit'], 'circle_state' => State::OUTER_CIRCLE->value, 'reason' => 'Rotation',
        ])->assertSessionHas('success');
        $this->assertState($applicant, State::OUTER_CIRCLE, $this->owner);
        $this->assertSame('Rotation', $this->podUser($applicant)->stateHistory()->get()->last()->reason);

        // Permission edits alone never change the circle.
        $this->actingAs($this->owner)->put(route('pod.users.updatePermissions', ['pod' => $this->pod, 'user' => $applicant]), [
            'permissions' => ['users_index', 'projects_index'],
        ])->assertSessionHas('success');
        $this->assertSame(State::OUTER_CIRCLE, $this->podUser($applicant)->state);
        $this->assertPermissions($this->podUser($applicant), ['users_index', 'news_index']);
        Bus::assertDispatchedTimes(SendEmail::class, 1); // the invitation email
    }

    public function test_state_endpoint_validates_and_authorizes(): void
    {
        $member = $this->membership('member', State::OUTER_CIRCLE);
        $manager = $this->membership('manager', State::OUTER_CIRCLE, ['users_edit']);
        $route = route('pod.users.changeState', ['pod' => $this->pod, 'user' => $member->user]);

        $this->actingAs($member->user)->postJson($route, ['state' => 'suspended', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($manager->user)->postJson($route, ['state' => 'nope'])->assertStatus(422);
        $this->actingAs($manager->user)->postJson($route, ['state' => 'suspended'])->assertStatus(422);              // reason required
        $this->actingAs($manager->user)->postJson($route, ['state' => 'screening_completed', 'reason' => 'x'])->assertStatus(422); // not in the graph
        $this->actingAs($manager->user)->postJson($route, ['state' => 'screening_in_progress'])->assertOk()->assertJsonPath('state', 'screening_in_progress');
        $this->actingAs($manager->user)->postJson($route, ['state' => 'suspended', 'reason' => 'Conduct'])->assertOk();
        $this->assertNoAccess($member->fresh(), 'declined');
    }

    public function test_project_claims_require_an_active_circle_state_and_access(): void // 48
    {
        $project = Project::forceCreate(['name' => 'P', 'workspace' => $this->podWorkspace->id, 'created_by' => $this->owner->id]);
        $outer = $this->membership('outer', State::OUTER_CIRCLE);
        $suspended = $this->membership('suspended', State::SUSPENDED);
        $applicant = $this->membership('applicant', State::PENDING_BUSINESS_APPROVAL);
        // Force active workspace access onto the suspended member: the state still denies.
        $this->workspaceOf($suspended)->forceFill(['status' => 'active', 'is_active' => 1])->save();

        $this->assertTrue(Gate::forUser($outer->user)->allows('create', [UserProject::class, $project]));
        $this->assertFalse(Gate::forUser($suspended->user)->allows('create', [UserProject::class, $project]), 'state denies despite access');
        $this->assertFalse(Gate::forUser($applicant->user)->allows('create', [UserProject::class, $project]));
        $this->workspaceOf($outer)->forceFill(['status' => 'declined'])->save();
        $this->assertFalse(Gate::forUser($outer->user)->allows('create', [UserProject::class, $project]), 'access denies despite state');
    }

    // ================================================================ helpers

    private function transitions(): PodStateTransitionService
    {
        return app(PodStateTransitionService::class);
    }

    private function user(string $name, UserRole $role): User
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

    /** A membership already in $state with consistent workspace access (fixture, bypasses the service). */
    private function membership(string $name, State $state, array $extra = []): PodUser
    {
        $user = $this->user($name, UserRole::EMPLOYEE);
        $membership = PodUser::forceCreate(['user_id' => $user->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => $state->value]);
        PodUserStateHistory::query()->forceCreate(['pod_user_id' => $membership->id, 'to_state' => $state->value, 'reason' => 'fixture']);
        $profile = $state->accessProfile();
        if ($state !== State::REMOVED) {
            $active = $profile !== 'none';
            UserWorkspace::create(['user_id' => $user->id, 'workspace_id' => $this->podWorkspace->id, 'permission' => 'Member', 'is_active' => $active ? 1 : 0,
                'status' => $active ? 'active' : (in_array($state, State::applicants(), true) ? 'employee_pending' : 'declined')])
                ->givePermissionTo($active ? $this->transitions()->permissionsFor($profile, $extra) : []);
        }
        if ($profile === 'inner' && in_array('projects_edit', $extra, true)) {
            $this->workspaceOf($membership)->givePermissionTo('projects_edit');
        }

        return $membership->fresh();
    }

    private function podUser(User $user): PodUser
    {
        return PodUser::where('pod_id', $this->pod->id)->where('user_id', $user->id)->sole();
    }

    private function workspaceOf(PodUser $membership): UserWorkspace
    {
        return UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $membership->user_id)->sole();
    }

    private function assertPermissions(PodUser $membership, array $expected, string $message = ''): void
    {
        $this->assertEqualsCanonicalizing($expected, $this->workspaceOf($membership)->getPermissionNames()->all(), $message);
    }

    private function assertNoAccess(PodUser $membership, string $workspaceStatus): void
    {
        $workspace = $this->workspaceOf($membership);
        $this->assertSame([$workspaceStatus, 0], [$workspace->status, (int) $workspace->is_active]);
        $this->assertSame([], $workspace->getPermissionNames()->all());
        $this->assertNotSame($this->podWorkspace->id, (int) $membership->user->fresh()->currant_workspace);
    }

    private function assertState(User $user, State $state, User $actor): void
    {
        $membership = $this->podUser($user);
        $this->assertSame($state, $membership->state);
        $last = $membership->stateHistory()->get()->last();
        $this->assertSame([$state, $actor->id], [$last->to_state, $last->actor_id]);
    }

    private function snapshot(): array
    {
        return collect(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'user_projects', 'tasks', 'model_has_permissions', 'notifications', 'users'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->map(fn ($r) => (array) $r)->sortBy(fn ($r) => json_encode($r))->values()->all()])
            ->all();
    }
}

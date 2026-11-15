<?php

namespace Tests\Feature\Profile;

use App\Enums\PodUser\State;
use App\Enums\User\UserRole;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\PodUserStateHistory;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\Membership\InvalidStateTransition;
use App\Services\Membership\PodStateTransitionService;
use App\Services\PodService;
use App\Services\PodUserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-007: an employee below the profile minimum may be invited or apply, but cannot enter (or
 * progress within) an active POD circle. A refused transition changes nothing. In-memory SQLite only.
 */
class PodProfileEligibilityTest extends TestCase
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

    public function test_an_incomplete_employee_can_be_invited_or_apply_but_not_enter_the_outer_circle(): void
    {
        $invitee = $this->user('invitee', UserRole::EMPLOYEE);
        $applicant = $this->user('applicant', UserRole::EMPLOYEE);
        $service = app(PodUserService::class);

        $this->assertTrue($service->inviteExistedUser($invitee->id, $this->pod->id, [], $this->owner)->applied);
        $this->assertTrue($service->requestJoinPod($applicant->id, $this->pod->id)->applied);
        $this->assertSame(State::MATCHED, $this->membership($invitee)->state);
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $this->membership($applicant)->state);
        Bus::fake(); // forget the invitation e-mail
        $before = $this->snapshot();

        // Accepting the invitation (the invitee) or the application (the owner, web and API).
        $this->actingAs($invitee)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $invitee]))
            ->assertStatus(422)->assertJsonPath('message', $this->refusal(0));
        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))
            ->assertStatus(422);
        $this->actingAs($this->owner)->postJson("/api/pods/{$this->pod->id}/requests/{$this->membership($applicant)->id}", ['action' => 'accept'])
            ->assertStatus(422)->assertJsonPath('message', $this->refusal(0));

        try {
            $service->respondToMembership($this->pod, $applicant, State::OUTER_CIRCLE, $this->owner);
            $this->fail('transition applied');
        } catch (InvalidStateTransition $e) {
            $this->assertSame($this->refusal(0), $e->getMessage());
        }

        // No state, history, workspace access, permission, notification or e-mail changed.
        $this->assertEquals($before, $this->snapshot());
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();

        // Declining still works for an incomplete employee.
        $this->assertTrue($service->respondToMembership($this->pod, $invitee, State::REJECTED, $invitee)->applied);
    }

    public function test_an_eighty_percent_employee_transitions_normally(): void
    {
        $applicant = $this->user('applicant', UserRole::EMPLOYEE);
        app(PodUserService::class)->requestJoinPod($applicant->id, $this->pod->id);
        $this->completeEmployeeProfile($applicant);
        // Drop two sections: 80% is exactly the minimum.
        DB::table('profile_preferred_roles')->delete();
        DB::table('profiles')->where('user_id', $applicant->id)->update(['linkedin_url' => null]);
        $this->assertSame(80, app(\App\Services\Profile\ProfileCompletionService::class)->percentage($applicant->fresh()));

        $this->actingAs($this->owner)->postJson(route('pods.accept', ['pod' => $this->pod, 'user' => $applicant]))->assertOk();

        $membership = $this->membership($applicant);
        $this->assertSame(State::OUTER_CIRCLE, $membership->state);
        $this->assertSame(['pending_business_approval', 'outer_circle'], $membership->stateHistory->map(fn ($h) => $h->to_state->value)->all());
        $this->assertSame(['active', 1], [$this->workspaceOf($applicant)->status, (int) $this->workspaceOf($applicant)->is_active]);
    }

    public function test_completing_the_profile_unblocks_the_same_transition(): void
    {
        $applicant = $this->user('applicant', UserRole::EMPLOYEE);
        app(PodUserService::class)->requestJoinPod($applicant->id, $this->pod->id);
        $membership = $this->membership($applicant);

        $this->expectRefusal(fn () => $this->transitions()->transition($membership, State::OUTER_CIRCLE, $this->owner));
        $this->completeEmployeeProfile($applicant);

        $this->assertTrue($this->transitions()->transition($membership, State::OUTER_CIRCLE, $this->owner)->applied);
    }

    public static function gatedTransitions(): array
    {
        return [
            'outer -> screening' => [State::OUTER_CIRCLE, State::SCREENING_IN_PROGRESS],
            'outer -> pending inner' => [State::OUTER_CIRCLE, State::PENDING_INNER_APPROVAL],
            'outer -> inner (direct promotion)' => [State::OUTER_CIRCLE, State::INNER_CIRCLE],
            'screening -> completed' => [State::SCREENING_IN_PROGRESS, State::SCREENING_COMPLETED],
            'pending inner -> inner' => [State::PENDING_INNER_APPROVAL, State::INNER_CIRCLE],
            'suspended -> outer (resume)' => [State::SUSPENDED, State::OUTER_CIRCLE],
            'suspended -> inner (resume)' => [State::SUSPENDED, State::INNER_CIRCLE],
            'matched -> outer' => [State::MATCHED, State::OUTER_CIRCLE],
        ];
    }

    #[DataProvider('gatedTransitions')]
    public function test_promotions_and_resumptions_into_active_states_are_refused_without_side_effects(State $from, State $to): void
    {
        $membership = $this->legacyMembership('member', $from);
        $before = $this->snapshot();

        $this->expectRefusal(fn () => $this->transitions()->transition($membership, $to, $this->owner, 'A reason'));

        $this->assertEquals($before, $this->snapshot());
        Bus::assertNothingDispatched();
        // updatePermissions() with an explicit circle uses the same path.
        if ($to === State::INNER_CIRCLE && $from->accessProfile() !== 'none') {
            $this->expectRefusal(fn () => app(PodUserService::class)->updatePermissions($this->pod, $membership->user, [], State::INNER_CIRCLE, $this->owner));
            $this->assertEquals($before, $this->snapshot());
        }
    }

    public function test_existing_incomplete_active_members_keep_access_and_can_step_down_or_leave(): void
    {
        $inner = $this->legacyMembership('inner', State::INNER_CIRCLE);
        $screening = $this->legacyMembership('screening', State::SCREENING_COMPLETED);
        $outer = $this->legacyMembership('outer', State::OUTER_CIRCLE);

        // Moves that do not grant more access are not gated.
        $this->assertTrue($this->transitions()->transition($inner, State::OUTER_CIRCLE, $this->owner, 'Demoted')->applied);
        $this->assertTrue($this->transitions()->transition($screening, State::OUTER_CIRCLE, $this->owner, 'Screening closed')->applied);
        $this->assertTrue($this->transitions()->transition($outer, State::SUSPENDED, $this->owner, 'Paused')->applied);
        $this->assertTrue(app(PodUserService::class)->remove($inner->user_id, $this->pod->id, $this->owner)->applied);
    }

    public function test_companies_are_not_subject_to_the_profile_rule(): void
    {
        $company = $this->user('other-company', UserRole::COMPANY);
        $membership = PodUser::forceCreate(['user_id' => $company->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => State::PENDING_BUSINESS_APPROVAL->value]);

        $this->assertTrue($this->transitions()->transition($membership, State::OUTER_CIRCLE, $this->owner)->applied);
    }

    // ================================================================ helpers

    private function transitions(): PodStateTransitionService
    {
        return app(PodStateTransitionService::class);
    }

    private function expectRefusal(\Closure $operation): void
    {
        try {
            $operation();
            $this->fail('transition applied');
        } catch (InvalidStateTransition $e) {
            $this->assertStringContainsString('80%', $e->getMessage());
        }
    }

    private function refusal(int $percentage): string
    {
        return __('This member\'s profile is :percentage% complete. At least :required% is required to join an active POD circle.', ['percentage' => $percentage, 'required' => 80]);
    }

    private function user(string $name, UserRole $role): User
    {
        $user = User::createWithAccountRole(['name' => $name, 'email' => "{$name}@example.test", 'plan' => 1, 'email_verified_at' => now()], $role);
        $workspace = Workspace::create(['name' => "{$name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner'])->assignAllPermissionsToCreator();
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return $user->fresh();
    }

    /** An incomplete employee already in $state (pre-UNA-007 data), with consistent access. */
    private function legacyMembership(string $name, State $state): PodUser
    {
        $user = $this->user($name, UserRole::EMPLOYEE);
        $membership = PodUser::forceCreate(['user_id' => $user->id, 'pod_id' => $this->pod->id, 'permission' => 'Member', 'state' => $state->value]);
        PodUserStateHistory::query()->forceCreate(['pod_user_id' => $membership->id, 'to_state' => $state->value, 'reason' => 'fixture']);
        $profile = $state->accessProfile();
        $active = $profile !== 'none';
        UserWorkspace::create(['user_id' => $user->id, 'workspace_id' => $this->podWorkspace->id, 'permission' => 'Member', 'is_active' => $active ? 1 : 0,
            'status' => $active ? 'active' : (in_array($state, State::applicants(), true) ? 'employee_pending' : 'declined')])
            ->givePermissionTo($active ? $this->transitions()->permissionsFor($profile, []) : []);

        return $membership->fresh();
    }

    private function membership(User $user): PodUser
    {
        return PodUser::where('pod_id', $this->pod->id)->where('user_id', $user->id)->sole();
    }

    private function workspaceOf(User $user): UserWorkspace
    {
        return UserWorkspace::where('workspace_id', $this->podWorkspace->id)->where('user_id', $user->id)->sole();
    }

    private function snapshot(): array
    {
        return collect(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions', 'notifications', 'users'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->map(fn ($r) => (array) $r)->sortBy(fn ($r) => json_encode($r))->values()->all()])
            ->all();
    }
}

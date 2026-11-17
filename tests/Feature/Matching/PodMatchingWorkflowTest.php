<?php

namespace Tests\Feature\Matching;

use App\Enums\Matching\MembershipAction;
use App\Enums\Matching\QualificationReason;
use App\Enums\PodUser\State;
use App\Enums\UserWorkspace\Permission;
use App\Models\PodMatch;
use App\Models\PodUser;
use App\Models\PodUserStateHistory;
use App\Models\UserWorkspace;
use App\Services\Matching\MatchQualification;
use App\Services\Matching\PodMatchingWorkflowService;
use App\Services\Matching\PodMatchPresenter;
use App\Services\Matching\PodMatchService;
use App\Services\Membership\PodStateTransitionService;
use Illuminate\Support\Facades\DB;

/** UNA-014: qualification and the three POD modes, applied through PodStateTransitionService only. */
class PodMatchingWorkflowTest extends WorkflowTestCase
{
    public function test_threshold_equality_qualifies_and_below_does_not(): void
    {
        $this->assertTrue(MatchQualification::meetsThreshold(7000, 70));
        $this->assertFalse(MatchQualification::meetsThreshold(6999, 70));
        $this->assertTrue(MatchQualification::meetsThreshold(0, 0));
        $this->assertSame(6999, MatchQualification::hundredths('69.99'));

        $this->configurePod('recommend_only', 68);
        $requirement = $this->partialRequirement();
        $candidate = $this->qualifiedEmployee();
        $outcome = $this->apply($requirement, $candidate); // 68.75
        $this->assertSame(['68.75', true, QualificationReason::QUALIFIED], [$outcome->match->score, $outcome->qualification->qualified, $outcome->qualification->reason]);

        $this->configurePod('recommend_only', 69);
        $outcome = $this->apply($requirement, $candidate);
        $this->assertSame([false, QualificationReason::BELOW_THRESHOLD, 69, 6875], [$outcome->qualification->qualified, $outcome->qualification->reason, $outcome->qualification->threshold, $outcome->qualification->scoreHundredths]);
        $this->assertSame('68.75', PodMatch::sole()->score, 'the threshold never changes the stored score');
    }

    public function test_a_disqualified_or_not_scorable_match_never_qualifies(): void
    {
        $this->configurePod('automatic', 0);
        $candidate = $this->qualifiedEmployee();

        // (25 + 15 + 0) / 50 = 80.00 with a threshold of 0, but the mandatory project type is not met.
        $mandatory = $this->perfectRequirement(['project_type' => 'hourly', 'mandatory_criteria' => ['project_type']]);
        $outcome = $this->apply($mandatory, $candidate);
        $this->assertSame([false, QualificationReason::NOT_ELIGIBLE, MembershipAction::NONE], [$outcome->qualification->qualified, $outcome->qualification->reason, $outcome->action]);
        $this->assertSame(8000, $outcome->qualification->scoreHundredths);

        $notScorable = $this->requirement(['preferred_work_mode' => 'remote']);
        $this->assertSame(QualificationReason::NOT_ELIGIBLE, $this->apply($notScorable, $candidate)->qualification->reason);
        $this->assertSame(0, PodUser::where('user_id', $candidate->id)->count());
    }

    public function test_recommend_only_keeps_a_recommendation_and_changes_no_membership(): void
    {
        $this->configurePod('recommend_only');
        $requirement = $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();
        $before = $this->memberships();

        $outcome = $this->apply($requirement, $candidate);

        $this->assertSame([true, MembershipAction::NONE, 'recommend_only', null], [$outcome->qualification->qualified, $outcome->action, $outcome->reason, $outcome->membershipState]);
        $this->assertEquals($before, $this->memberships());
        $this->assertSame(1, PodMatch::count());
    }

    public function test_pending_mode_enters_pending_business_approval_through_the_transition_service(): void
    {
        $this->configurePod('pending_approval');
        $candidate = $this->qualifiedEmployee();

        $outcome = $this->apply($this->perfectRequirement(), $candidate);

        $this->assertSame([MembershipAction::CREATED, State::PENDING_BUSINESS_APPROVAL], [$outcome->action, $outcome->membershipState]);
        $membership = PodUser::where('pod_id', $this->pod->id)->where('user_id', $candidate->id)->sole();
        $history = PodUserStateHistory::where('pod_user_id', $membership->id)->sole();
        $this->assertSame([null, 'pending_business_approval', null, PodMatchingWorkflowService::PENDING_REASON],
            [$history->from_state, $history->to_state->value, $history->actor_id, $history->reason]);
        $userWorkspace = UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $candidate->id)->sole();
        $this->assertSame([0, []], [(int) $userWorkspace->is_active, $userWorkspace->getPermissionNames()->all()]);
        $this->assertSame(1, DB::table('notifications')->where('type', 'pod_request')->where('user_id', $this->company->id)->count(), 'owner notified after commit');
    }

    public function test_automatic_mode_places_in_the_outer_circle_with_history_and_access(): void
    {
        $this->configurePod('automatic');
        $candidate = $this->qualifiedEmployee();

        $outcome = $this->apply($this->perfectRequirement(), $candidate);

        $this->assertSame([MembershipAction::CREATED, State::OUTER_CIRCLE], [$outcome->action, $outcome->membershipState]);
        $membership = PodUser::where('pod_id', $this->pod->id)->where('user_id', $candidate->id)->sole();
        $this->assertSame('Member', $membership->permission);
        $history = PodUserStateHistory::where('pod_user_id', $membership->id)->sole();
        $this->assertSame([null, 'outer_circle', null, 'Qualified automatically by POD matching'], [$history->from_state, $history->to_state->value, $history->actor_id, $history->reason]);

        $userWorkspace = UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $candidate->id)->sole();
        $this->assertSame(1, (int) $userWorkspace->is_active);
        $this->assertEqualsCanonicalizing([Permission::USERS_INDEX->value, Permission::NEWS_INDEX->value], $userWorkspace->getPermissionNames()->all());
        $this->assertNotContains(Permission::PROJECTS_INDEX->value, $userWorkspace->getPermissionNames()->all(), 'never Inner Circle access');
    }

    public function test_automatic_placement_obeys_profile_completion(): void
    {
        $this->configurePod('automatic');
        $candidate = $this->incompleteEmployee();
        $before = $this->memberships();

        $outcome = $this->apply($this->perfectRequirement(), $candidate);

        $this->assertSame([true, MembershipAction::BLOCKED, 'profile_incomplete'], [$outcome->qualification->qualified, $outcome->action, $outcome->reason]);
        $this->assertEquals($before, $this->memberships());

        // An incomplete applicant in automatic mode stays an applicant.
        $this->configurePod('pending_approval');
        $this->assertSame(MembershipAction::CREATED, $this->apply($this->perfectRequirement(), $candidate)->action);
        $this->configurePod('automatic');
        $outcome = $this->apply($this->perfectRequirement(), $candidate);
        $this->assertSame([MembershipAction::BLOCKED, State::PENDING_BUSINESS_APPROVAL], [$outcome->action, $outcome->membershipState]);
    }

    public function test_a_draft_pod_never_creates_or_advances_memberships(): void
    {
        $this->configurePod('automatic', 0, published: false);
        $candidate = $this->qualifiedEmployee();
        $before = $this->memberships();

        $outcome = $this->apply($this->perfectRequirement(), $candidate);

        $this->assertSame([false, QualificationReason::POD_NOT_PUBLISHED, MembershipAction::NONE], [$outcome->qualification->qualified, $outcome->qualification->reason, $outcome->action]);
        $this->assertEquals($before, $this->memberships());
        $this->assertSame('100.00', PodMatch::sole()->score, 'drafts may still store scores');
    }

    public function test_established_memberships_are_never_overwritten(): void
    {
        $this->configurePod('automatic', 0);
        $requirement = $this->perfectRequirement();

        foreach ([State::REJECTED, State::REMOVED, State::SUSPENDED, State::OUTER_CIRCLE, State::SCREENING_IN_PROGRESS, State::INNER_CIRCLE] as $state) {
            $candidate = $this->qualifiedEmployee();
            PodUser::query()->forceCreate(['pod_id' => $this->pod->id, 'user_id' => $candidate->id, 'permission' => 'Member', 'state' => $state->value]); // fixture
            $before = $this->memberships();

            $outcome = $this->apply($requirement, $candidate);

            $this->assertSame([true, MembershipAction::UNCHANGED, $state, 'already_member'], [$outcome->qualification->qualified, $outcome->action, $outcome->membershipState, $outcome->reason], $state->value);
            $this->assertEquals($before, $this->memberships(), $state->value);
        }
    }

    public function test_automatic_advances_an_applicant_through_the_transition_graph(): void
    {
        $this->configurePod('pending_approval');
        $requirement = $this->perfectRequirement();
        $applicant = $this->qualifiedEmployee();
        $invited = $this->qualifiedEmployee();
        $this->apply($requirement, $applicant);
        app(PodStateTransitionService::class)->enter($this->pod, $invited, State::MATCHED, $this->company);

        $this->configurePod('automatic');
        foreach ([$applicant, $invited] as $candidate) {
            $outcome = $this->apply($requirement, $candidate);
            $this->assertSame([MembershipAction::ADVANCED, State::OUTER_CIRCLE], [$outcome->action, $outcome->membershipState]);
            $membership = PodUser::where('pod_id', $this->pod->id)->where('user_id', $candidate->id)->sole();
            $this->assertSame([State::OUTER_CIRCLE->value, null, PodMatchingWorkflowService::AUTOMATIC_REASON],
                [($h = PodUserStateHistory::where('pod_user_id', $membership->id)->orderByDesc('id')->first())->to_state->value, $h->actor_id, $h->reason]);
            $this->assertSame(1, (int) UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $candidate->id)->value('is_active'));
        }
    }

    public function test_repeated_processing_is_idempotent_and_never_duplicates(): void
    {
        $this->configurePod('automatic');
        $requirement = $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();

        $first = $this->apply($requirement, $candidate);
        $after = $this->memberships();
        $second = $this->apply($requirement, $candidate);
        $third = $this->apply($requirement, $candidate);

        $this->assertSame([MembershipAction::CREATED, MembershipAction::UNCHANGED, MembershipAction::UNCHANGED], [$first->action, $second->action, $third->action]);
        $this->assertEquals($after, $this->memberships());
        $this->assertSame([1, 1, 1], [
            PodUser::where('user_id', $candidate->id)->count(),
            PodUserStateHistory::count() - 1, // minus the owner's initial row
            UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $candidate->id)->count(),
        ]);
        $this->assertSame(1, PodMatch::count());

        // A row that appears concurrently in a re-entry state is not resurrected by system matching.
        $late = $this->qualifiedEmployee();
        PodUser::query()->forceCreate(['pod_id' => $this->pod->id, 'user_id' => $late->id, 'permission' => 'Member', 'state' => State::REJECTED->value]);
        $result = app(PodStateTransitionService::class)->enter($this->pod, $late, State::OUTER_CIRCLE, null, 'x', [], false);
        $this->assertSame([false, 'reentry_not_allowed', State::REJECTED], [$result->applied, $result->reason, PodUser::where('user_id', $late->id)->sole()->state]);
    }

    public function test_the_presenter_and_resource_include_threshold_and_qualification(): void
    {
        $this->configurePod('recommend_only', 69);
        $match = $this->apply($this->partialRequirement(), $this->qualifiedEmployee())->match;

        $presented = app(PodMatchPresenter::class)->present(PodMatch::find($match->id));
        $this->assertSame(['68.75', 69, false, 'below_threshold', 'Below the POD minimum match score.', 'eligible', []],
            [$presented['score'], $presented['threshold'], $presented['qualified'], $presented['qualification']['code'], $presented['qualification']['label'], $presented['status'], $presented['failed_mandatory']]);
        $this->assertCount(9, $presented['criteria']);

        $this->configurePod('recommend_only', 60);
        $json = (new \App\Http\Resources\PodMatchResource(PodMatch::with('reasons', 'requirement.pod')->find($match->id)))->resolve();
        $this->assertSame([60, true, 'qualified'], [$json['threshold'], $json['qualified'], $json['qualification']['code']]);
    }

    public function test_only_employees_reach_the_workflow(): void
    {
        $this->configurePod('automatic', 0);
        $this->expectException(\App\Services\Matching\IneligibleCandidate::class);
        $this->apply($this->perfectRequirement(), $this->account('company'));
    }

    public function test_the_service_api_alone_stays_information_only(): void
    {
        $this->configurePod('automatic', 0);
        $requirement = $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();
        $before = $this->memberships();
        app(PodMatchService::class)->evaluate($requirement, $candidate, $this->at());
        $this->assertEquals($before, $this->memberships());
    }
}

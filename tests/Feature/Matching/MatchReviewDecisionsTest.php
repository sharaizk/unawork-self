<?php

namespace Tests\Feature\Matching;

use App\Enums\Matching\MatchReviewAction as Action;
use App\Enums\Matching\MatchReviewDecision;
use App\Enums\PodUser\State;
use App\Enums\UserWorkspace\Permission;
use App\Jobs\Matching\GenerateEmployeePodMatch;
use App\Jobs\Matching\GenerateMatchesForEmployee;
use App\Jobs\Matching\GenerateMatchesForPod;
use App\Models\PodMatch;
use App\Models\PodMatchingExclusion;
use App\Models\PodMatchReason;
use App\Models\PodMatchReview;
use App\Models\PodUser;
use App\Models\PodUserStateHistory;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Services\Matching\MatchReviewConflict;
use App\Services\Matching\MatchReviewNotApprovable;
use App\Services\Matching\PodMatchingExclusionService;
use App\Services\Matching\PodMatchingWorkflowService;
use App\Services\Matching\PodMatchReviewDecisionService;
use App\Services\Matching\PodRecommendationService;
use App\Services\PodUserService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/** UNA-018: match-review provenance, decisions, exclusions and lifecycle. In-memory SQLite only. */
class MatchReviewDecisionsTest extends WorkflowTestCase
{
    private function decisions(): PodMatchReviewDecisionService
    {
        return app(PodMatchReviewDecisionService::class);
    }

    /** A qualified matching proposal in a pending-approval POD. @return array{User, PodUser, PodMatchReview} */
    private function proposal(?User $candidate = null, $requirement = null): array
    {
        $this->pod ?? $this->configurePod('pending_approval', 70);
        $requirement ??= $this->pod->workerRequirements()->first() ?? $this->perfectRequirement();
        $candidate ??= $this->qualifiedEmployee();
        $this->apply($requirement, $candidate);
        $membership = PodUser::where('pod_id', $this->pod->id)->where('user_id', $candidate->id)->sole();

        return [$candidate, $membership, PodMatchReview::where('pod_user_id', $membership->id)->sole()];
    }

    private function histories(PodUser $membership)
    {
        return PodUserStateHistory::where('pod_user_id', $membership->id)->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ provenance

    public function test_pending_matching_creates_the_membership_and_its_review_atomically(): void
    {
        [$user, $membership, $review] = $this->proposal();
        $this->assertSame([State::PENDING_BUSINESS_APPROVAL, null, null], [$membership->state, $review->resolved_at, $review->decision]);
        $this->assertSame(PodMatch::where('user_id', $user->id)->value('id'), $review->source_pod_match_id);

        // Repeated matching: no duplicate review.
        $this->apply($this->pod->workerRequirements()->first(), $user);
        $this->assertSame(1, PodMatchReview::count());

        // A failing review insert rolls the membership back too.
        DB::statement("CREATE TRIGGER fail_review BEFORE INSERT ON pod_match_reviews BEGIN SELECT RAISE(ABORT, 'injected'); END");
        $other = $this->qualifiedEmployee();
        try {
            $this->apply($this->pod->workerRequirements()->first(), $other);
            $this->fail('escaped');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, PodUser::where('user_id', $other->id)->count());
        Bus::assertNothingDispatched();
    }

    public function test_no_review_for_manual_applications_other_modes_or_unqualified_matches(): void
    {
        $this->configurePod('pending_approval', 70);
        $requirement = $this->perfectRequirement();
        $manual = $this->qualifiedEmployee();
        app(PodUserService::class)->requestJoinPod($manual->id, $this->pod->id);

        $this->configurePod('recommend_only');
        $this->apply($requirement, $this->qualifiedEmployee());
        $this->configurePod('automatic');
        $this->apply($requirement, $auto = $this->qualifiedEmployee());
        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $auto->id)->sole()->state);
        $this->configurePod('pending_approval', 100);
        $this->apply($this->partialRequirement(), $this->qualifiedEmployee()); // 68.75 < 100

        $this->assertSame(0, PodMatchReview::count());
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $manual->id)->sole()->state);
    }

    public function test_backfill_classifies_only_current_matching_proposals(): void
    {
        [$matched, $pending] = $this->proposal();
        [, $decided] = $this->proposal();
        $this->decisions()->decide($this->pod, PodMatchReview::where('pod_user_id', $decided->id)->sole(), Action::APPROVE, $this->company);
        $manual = $this->qualifiedEmployee();
        app(PodUserService::class)->requestJoinPod($manual->id, $this->pod->id);

        $migration = require database_path('migrations/2026_11_19_000500_create_pod_match_reviews_table.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('pod_match_reviews'));
        $migration->up();

        $this->assertSame([[$pending->id, null]], PodMatchReview::get()->map(fn ($r) => [(int) $r->pod_user_id, $r->source_pod_match_id])->all());
    }

    // ------------------------------------------------------------------ approve

    public function test_approval_places_in_the_outer_circle_with_history_and_resolves_the_review(): void
    {
        [$user, $membership, $review] = $this->proposal();

        $this->decisions()->decide($this->pod, $review, Action::APPROVE, $this->company);

        $this->assertSame(State::OUTER_CIRCLE, $membership->fresh()->state);
        $last = $this->histories($membership)->last();
        $this->assertSame(['pending_business_approval', 'outer_circle', $this->company->id, 'Match approved by business'],
            [$last->from_state->value, $last->to_state->value, (int) $last->actor_id, $last->reason]);
        $this->assertNotNull($last->created_at);
        $this->assertSame([MatchReviewDecision::APPROVED, $this->company->id, null], [$review->fresh()->decision, (int) $review->fresh()->decided_by, $review->fresh()->decision_reason]);

        $access = UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $user->id)->sole();
        $this->assertSame(1, (int) $access->is_active);
        $this->assertEqualsCanonicalizing([Permission::USERS_INDEX->value, Permission::NEWS_INDEX->value], $access->getPermissionNames()->all());

        // A second (racing) approval is a conflict and writes nothing.
        try {
            $this->decisions()->decide($this->pod, $review, Action::APPROVE, $this->company);
            $this->fail('approved twice');
        } catch (MatchReviewConflict) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(2, $this->histories($membership)->count());
    }

    public function test_approval_is_refused_when_the_match_no_longer_qualifies_or_the_pair_is_excluded(): void
    {
        [, $membership, $review] = $this->proposal();
        $this->configurePod('pending_approval', 100);
        $this->pod->workerRequirements()->first()->skills()->sync([$this->php->id, $this->docker->id]);
        $this->apply($this->pod->workerRequirements()->first(), $membership->user); // regenerated: 68.75 < 100

        $this->assertNotApprovable($review);

        [$user2, $membership2, $review2] = $this->proposal(null, $this->perfectRequirement());
        app(PodMatchingExclusionService::class)->exclude($this->pod, $user2, $this->company);
        $this->assertNotApprovable($review2);
        $this->assertSame([State::PENDING_BUSINESS_APPROVAL, State::PENDING_BUSINESS_APPROVAL], [$membership->fresh()->state, $membership2->fresh()->state]);
        $this->assertSame(2, PodMatchReview::unresolved()->count());
    }

    private function assertNotApprovable(PodMatchReview $review): void
    {
        try {
            $this->decisions()->decide($this->pod, $review, Action::APPROVE, $this->company);
            $this->fail('approved');
        } catch (MatchReviewNotApprovable) {
            $this->addToAssertionCount(1);
        }
    }

    // ------------------------------------------------------------------ reject

    public function test_rejection_uses_the_optional_reason_or_a_stable_fallback(): void
    {
        [, $blank, $blankReview] = $this->proposal();
        [, $reasoned, $reasonedReview] = $this->proposal();

        $this->decisions()->decide($this->pod, $blankReview, Action::REJECT, $this->company, '   ');
        $this->decisions()->decide($this->pod, $reasonedReview, Action::REJECT, $this->company, 'Looking for more SQL depth');

        foreach ([[$blank, $blankReview, 'Match rejected by business', null], [$reasoned, $reasonedReview, 'Looking for more SQL depth', 'Looking for more SQL depth']] as [$membership, $review, $history, $note]) {
            $this->assertSame(State::REJECTED, $membership->fresh()->state);
            $last = $this->histories($membership)->last();
            $this->assertSame(['pending_business_approval', 'rejected', $this->company->id, $history], [$last->from_state->value, $last->to_state->value, (int) $last->actor_id, $last->reason]);
            $this->assertSame([MatchReviewDecision::REJECTED, $note], [$review->fresh()->decision, $review->fresh()->decision_reason]);
            $this->assertSame(2, $this->histories($membership)->count());
        }

        $this->expectException(MatchReviewConflict::class);
        $this->decisions()->decide($this->pod, $blankReview, Action::REJECT, $this->company);
    }

    public function test_manual_moderation_keeps_its_rules_and_resolves_a_matching_review_consistently(): void
    {
        [, $membership, $review] = $this->proposal();
        $manual = $this->qualifiedEmployee();
        app(PodUserService::class)->requestJoinPod($manual->id, $this->pod->id);
        $manualMembership = PodUser::where('user_id', $manual->id)->sole();
        Sanctum::actingAs($this->company);

        $url = fn (PodUser $m) => url("/api/pods/{$this->pod->id}/requests/{$m->id}");
        $this->postJson($url($manualMembership), ['action' => 'decline'])->assertStatus(422)->assertJsonValidationErrors('reason'); // unchanged rule
        $this->postJson($url($membership), ['action' => 'accept'])->assertOk();

        $this->assertSame(State::OUTER_CIRCLE, $membership->fresh()->state);
        $this->assertSame([MatchReviewDecision::APPROVED, $this->company->id], [$review->fresh()->decision, (int) $review->fresh()->decided_by]);
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $manualMembership->fresh()->state);
        $this->assertNull($manualMembership->matchReview);
    }

    // ------------------------------------------------------------------ exclude

    public function test_exclusion_rejects_and_excludes_atomically_and_idempotently(): void
    {
        [$user, $membership, $review] = $this->proposal();

        $this->decisions()->decide($this->pod, $review, Action::EXCLUDE, $this->company, 'Not a fit for client work');

        $exclusion = PodMatchingExclusion::sole();
        $this->assertSame([$this->pod->id, $user->id, $this->company->id, 'Not a fit for client work'], [(int) $exclusion->pod_id, (int) $exclusion->user_id, (int) $exclusion->excluded_by, $exclusion->reason]);
        $this->assertSame(State::REJECTED, $membership->fresh()->state);
        $last = $this->histories($membership)->last();
        $this->assertSame(['rejected', $this->company->id, 'Excluded from future POD matching: Not a fit for client work'], [$last->to_state->value, (int) $last->actor_id, $last->reason]);
        $this->assertSame(MatchReviewDecision::EXCLUDED, $review->fresh()->decision);
        $this->assertGreaterThan(0, PodMatchReason::count(), 'history kept for audit');
        $this->assertSame(1, PodMatch::where('user_id', $user->id)->count());

        app(PodMatchingExclusionService::class)->exclude($this->pod, $user, $this->company, 'again');
        $this->assertSame([1, 'Not a fit for client work'], [PodMatchingExclusion::count(), PodMatchingExclusion::sole()->reason]);

        // Without a reason: the stable audit reason alone.
        [, $plain, $plainReview] = $this->proposal();
        $this->decisions()->decide($this->pod, $plainReview, Action::EXCLUDE, $this->company);
        $this->assertSame('Excluded from future POD matching', $this->histories($plain)->last()->reason);
    }

    public function test_a_failing_exclusion_leaves_nothing_behind(): void
    {
        [, $membership, $review] = $this->proposal();
        DB::statement("CREATE TRIGGER fail_history BEFORE INSERT ON pod_user_state_histories WHEN NEW.to_state = 'rejected' BEGIN SELECT RAISE(ABORT, 'injected'); END");

        try {
            $this->decisions()->decide($this->pod, $review, Action::EXCLUDE, $this->company, 'x');
            $this->fail('escaped');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([0, State::PENDING_BUSINESS_APPROVAL, null], [PodMatchingExclusion::count(), $membership->fresh()->state, $review->fresh()->resolved_at]);
        $this->assertSame(1, $this->histories($membership)->count());
    }

    public function test_exclusions_are_enforced_by_every_matching_layer_and_are_not_a_pod_ban(): void
    {
        $this->configurePod('automatic', 0);
        $requirement = $this->perfectRequirement();
        [$excluded, $other] = [$this->qualifiedEmployee(), $this->qualifiedEmployee()];
        $this->apply($requirement, $excluded); // a match exists before the exclusion
        PodUser::where('user_id', $excluded->id)->sole(); // ... and an Outer Circle membership
        app(PodMatchingExclusionService::class)->exclude($this->pod, $excluded, $this->company);
        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $excluded->id)->sole()->state, 'never removes an existing member');

        $fresh = $this->qualifiedEmployee();
        app(PodMatchingExclusionService::class)->exclude($this->pod, $fresh, $this->company);

        // Pair job, coordinators and the workflow guard.
        $this->assertSame('skipped_excluded', $this->runJob(new GenerateEmployeePodMatch($fresh->id, $this->pod->id, now()->toIso8601String())));
        $this->runJob(new GenerateMatchesForPod($this->pod->id, now()->toIso8601String()));
        $paired = Bus::dispatched(GenerateEmployeePodMatch::class)->map(fn ($j) => $j->userId)->all();
        $this->assertNotContains($fresh->id, $paired);
        $this->assertNotContains($excluded->id, $paired);
        $this->assertContains($other->id, $paired, 'other employees still match this POD');

        $second = $this->pods()->createDraft($this->company, ['title' => 'Second']);
        $second->forceFill(['status' => 'published', 'published_at' => now(), 'matching_mode' => 'automatic', 'minimum_match_threshold' => 0])->save();
        $secondRequirement = $this->requirementOn($second);
        Bus::fake();
        $this->runJob(new GenerateMatchesForEmployee($fresh->id, now()->toIso8601String()));
        $this->assertSame([$second->id], Bus::dispatched(GenerateEmployeePodMatch::class)->map(fn ($j) => $j->podId)->all(), 'other PODs still match this employee');

        $outcome = app(PodMatchingWorkflowService::class)->evaluateAndApply($requirement->fresh(), $fresh->fresh(), $this->at());
        $this->assertSame([\App\Enums\Matching\MembershipAction::NONE, 'excluded'], [$outcome->action, $outcome->reason]);
        $this->assertSame(0, PodUser::where('pod_id', $this->pod->id)->where('user_id', $fresh->id)->count());
        $this->assertSame(1, PodMatch::where('pod_id', $this->pod->id)->where('user_id', $fresh->id)->count(), 'match data is kept');

        // Recommendations hide it; the POD stays discoverable and open to a manual application.
        $this->pod->forceFill(['matching_mode' => 'recommend_only'])->save();
        $this->assertNotContains($this->pod->id, app(PodRecommendationService::class)->rankedMatches($fresh->fresh())->pluck('pod_id')->all());
        $this->assertTrue($fresh->can('requestToJoin', $this->pod->fresh()));
        $this->apply($secondRequirement, $fresh);
        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('pod_id', $second->id)->where('user_id', $fresh->id)->sole()->state);
    }

    // ------------------------------------------------------------------ lifecycle

    public function test_automatic_advancement_and_later_manual_reentry_keep_reviews_resolved(): void
    {
        [, $membership, $review] = $this->proposal();
        $this->configurePod('automatic', 70);
        $this->apply($this->pod->workerRequirements()->first(), $membership->user);
        $this->assertSame(State::OUTER_CIRCLE, $membership->fresh()->state);
        $this->assertSame([MatchReviewDecision::AUTOMATICALLY_ADVANCED, null], [$review->fresh()->decision, $review->fresh()->decided_by]);

        $this->configurePod('pending_approval', 70);
        [$user, $rejected, $rejectedReview] = $this->proposal();
        $this->decisions()->decide($this->pod, $rejectedReview, Action::REJECT, $this->company);
        app(PodUserService::class)->requestJoinPod($user->id, $this->pod->id); // manual re-entry of the same row
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, $rejected->fresh()->state);
        $this->assertSame(0, PodMatchReview::unresolved()->count(), 'the old review stays resolved; it is a manual application now');
    }

    public function test_foreign_keys_cascade_or_null_as_designed_and_migrations_reverse(): void
    {
        [$user, $membership, $review] = $this->proposal();
        [$excludedUser, , $excludedReview] = $this->proposal();
        $manager = $this->qualifiedEmployee();
        $this->decisions()->decide($this->pod, $excludedReview, Action::EXCLUDE, $manager->forceFill([])); // decided_by = $manager

        DB::table('pod_matches')->where('user_id', $user->id)->delete();
        $this->assertNull($review->fresh()->source_pod_match_id, 'provenance survives match regeneration');
        DB::table('users')->where('id', $manager->id)->delete();
        $this->assertNull($excludedReview->fresh()->decided_by);
        $this->assertNull(PodMatchingExclusion::sole()->excluded_by);
        DB::table('pod_users')->where('id', $membership->id)->delete();
        $this->assertNull($review->fresh(), 'review deleted with its membership');
        DB::table('users')->where('id', $excludedUser->id)->delete();
        $this->assertSame(0, PodMatchingExclusion::count(), 'exclusion deleted with the user');

        foreach (['2026_11_19_000600_create_pod_matching_exclusions_table.php' => 'pod_matching_exclusions', '2026_11_19_000500_create_pod_match_reviews_table.php' => 'pod_match_reviews'] as $file => $table) {
            (require database_path("migrations/{$file}"))->down();
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable($table));
        }
    }

    public function test_pod_deletion_removes_its_exclusions(): void
    {
        $this->configurePod('pending_approval');
        app(PodMatchingExclusionService::class)->exclude($this->pod, $this->qualifiedEmployee(), $this->company);
        DB::table('pods')->where('id', $this->pod->id)->delete();
        $this->assertSame(0, PodMatchingExclusion::count());
    }

    private function requirementOn($pod): \App\Models\PodWorkerRequirement
    {
        $requirement = new \App\Models\PodWorkerRequirement(['is_active' => true, 'headcount' => 1, 'job_title_id' => $this->engineer->id]);
        $requirement->forceFill(['pod_id' => $pod->id])->save();
        $requirement->skills()->sync([$this->php->id]);

        return $requirement->fresh();
    }
}

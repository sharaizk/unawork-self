<?php

namespace App\Services\Matching;

use App\Enums\Matching\MembershipAction;
use App\Enums\Pod\PodMatchingMode;
use App\Enums\PodUser\State;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodUser;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use App\Services\Membership\AfterCommit;
use App\Services\Membership\InvalidStateTransition;
use App\Services\Membership\MembershipResult;
use App\Services\Membership\PodStateTransitionService;
use App\Services\Profile\ProfileCompletionService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * The single application entry point that turns a match into a POD action (UNA-014):
 *
 *   1. score + persist through PodMatchService (information only),
 *   2. qualify with MatchQualification (eligible, score >= POD threshold, active requirement, published POD),
 *   3. apply the POD matching mode through PodStateTransitionService only:
 *        recommend_only   → nothing
 *        pending_approval → a candidate without any membership enters pending_business_approval
 *        automatic        → a candidate without any membership enters outer_circle (profile gate),
 *   (UNA-018: a pending_approval proposal gets its pod_match_reviews row in the same transaction;
 *    an excluded employee/POD pair is never placed),
 *                           an applicant (matched / pending_business_approval) advances to outer_circle
 *
 * Forward-only: an existing membership in any other state (rejected, removed, suspended, Outer or
 * Inner Circle, screening) is never changed, nothing is removed or demoted, nobody reaches the
 * Inner Circle, and rejected/removed rows are not re-entered. Effects (notifications) run after
 * the membership transaction commits (AfterCommit). Repeated calls are idempotent.
 */
class PodMatchingWorkflowService
{
    private const ATTEMPTS = 3;

    // Stored in pod_user_state_histories.reason (actor = null, a system action).
    public const AUTOMATIC_REASON = 'Qualified automatically by POD matching';
    public const PENDING_REASON = 'Matched by POD matching, awaiting approval';

    public function __construct(
        private PodMatchService $matches,
        private PodStateTransitionService $transitions,
        private AfterCommit $afterCommit,
        private ProfileCompletionService $completion,
        private PodMatchingExclusionService $exclusions,
    ) {
    }

    /**
     * Evaluate $candidate against $requirement, persist the match and apply the POD's mode.
     *
     * @throws IneligibleCandidate|InactiveRequirement
     */
    public function evaluateAndApply(PodWorkerRequirement $requirement, User $candidate, ?DateTimeInterface $at = null): PodMatchOutcome
    {
        $result = $this->matches->score($requirement, $candidate, $at);
        $generation = $this->matches->persistGeneration($candidate, [[$requirement, $result]], DeterministicPodMatchingService::KEY);
        $match = $generation->matches[$requirement->id];
        $pod = $requirement->relationLoaded('pod') ? $requirement->pod : $requirement->pod()->first();
        $qualification = MatchQualification::of($match, $requirement, $pod);

        if ($generation->isStale()) {
            // UNA-016: a newer generation is stored; never act on an older one.
            return new PodMatchOutcome($match, $qualification, MembershipAction::NONE, $this->currentState($pod, $candidate), 'stale_generation');
        }

        return $this->apply($match, $qualification, $pod, $candidate);
    }

    /**
     * Apply the POD mode to a match that is already persisted, e.g. the best match of a
     * DeterministicPodMatchingService run (UNA-015). Nothing is re-scored: qualification uses the
     * stored score/status and the POD's current threshold.
     */
    public function applyPersisted(PodMatch $match): PodMatchOutcome
    {
        $match->loadMissing(['requirement.pod', 'user', 'reasons']);
        $pod = $match->requirement->pod;

        return $this->apply($match, MatchQualification::of($match, $match->requirement, $pod), $pod, $match->user);
    }

    /** Apply the POD mode to an already persisted, qualified-or-not match. */
    public function apply(PodMatch $match, MatchQualification $qualification, Pod $pod, User $candidate): PodMatchOutcome
    {
        $mode = $pod->matching_mode;
        if (!$qualification->qualified) {
            return new PodMatchOutcome($match, $qualification, MembershipAction::NONE, $this->currentState($pod, $candidate), $qualification->reason->value);
        }
        if (!$mode instanceof PodMatchingMode || !$mode->actsOnMemberships()) {
            return new PodMatchOutcome($match, $qualification, MembershipAction::NONE, $this->currentState($pod, $candidate), 'recommend_only');
        }

        $target = $mode === PodMatchingMode::AUTOMATIC ? State::OUTER_CIRCLE : State::PENDING_BUSINESS_APPROVAL;

        try {
            [$action, $result] = DB::transaction(function () use ($pod, $candidate, $mode, $target, $match) {
                $existing = PodUser::query()->where('pod_id', $pod->id)->where('user_id', $candidate->id)->lockForUpdate()->first();

                // UNA-018: last line of defence; an excluded pair is never placed, whatever called us.
                if ($this->exclusions->isExcluded($pod, $candidate)) {
                    return [MembershipAction::NONE, MembershipResult::unchanged($existing, 'excluded')];
                }
                $wouldPlace = $existing === null || ($mode === PodMatchingMode::AUTOMATIC && in_array($existing->state, State::applicants(), true));

                // Automatic placement obeys the profile minimum (the transition service checks it
                // again under its own lock); an existing applicant then stays where it is.
                if ($wouldPlace && $target === State::OUTER_CIRCLE && !$this->completion->isEligible($candidate)) {
                    return [MembershipAction::BLOCKED, MembershipResult::unchanged($existing, 'profile_incomplete')];
                }

                if ($existing === null) {
                    // No re-entry: a concurrently created rejected/removed row is left alone.
                    $reason = $target === State::OUTER_CIRCLE ? self::AUTOMATIC_REASON : self::PENDING_REASON;

                    $started = $this->transitions->start($pod, $candidate, $target, null, $reason, [], false);
                    if ($started->applied && $target === State::PENDING_BUSINESS_APPROVAL) {
                        // UNA-018: explicit provenance, created with the membership (same transaction).
                        $this->recordReview((int) $started->membership->id, (int) $match->id);
                    }

                    return [MembershipAction::CREATED, $started];
                }
                if ($mode === PodMatchingMode::AUTOMATIC && in_array($existing->state, State::applicants(), true)) {
                    return [MembershipAction::ADVANCED, $this->transitions->apply($existing, State::OUTER_CIRCLE, null, self::AUTOMATIC_REASON, State::applicants())];
                }

                return [MembershipAction::UNCHANGED, MembershipResult::unchanged($existing, 'already_member')];
            }, self::ATTEMPTS);
        } catch (InvalidStateTransition) {
            return new PodMatchOutcome($match, $qualification, MembershipAction::BLOCKED, $this->currentState($pod, $candidate), 'transition_refused');
        }

        $this->afterCommit->run($result);

        return new PodMatchOutcome(
            $match,
            $qualification,
            $result->applied || in_array($action, [MembershipAction::BLOCKED, MembershipAction::NONE], true) ? $action : MembershipAction::UNCHANGED,
            $this->currentState($pod, $candidate),
            $result->applied ? null : $result->reason,
        );
    }

    /** One review per membership (unique pod_user_id): a retry or race never duplicates it. */
    private function recordReview(int $podUserId, int $matchId): void
    {
        $now = now();
        DB::table('pod_match_reviews')->insertOrIgnore([
            'pod_user_id' => $podUserId,
            'source_pod_match_id' => $matchId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function currentState(Pod $pod, User $candidate): ?State
    {
        return PodUser::query()->where('pod_id', $pod->id)->where('user_id', $candidate->id)->first()?->state;
    }
}

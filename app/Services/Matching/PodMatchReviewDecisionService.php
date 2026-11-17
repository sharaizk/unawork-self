<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchReviewAction;
use App\Enums\Matching\MatchReviewDecision;
use App\Enums\PodUser\State;
use App\Models\Pod;
use App\Models\PodMatchReview;
use App\Models\PodUser;
use App\Models\User;
use App\Services\Membership\AfterCommit;
use App\Services\Membership\MembershipResult;
use App\Services\Membership\PodStateTransitionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Business decisions on a matching proposal (UNA-018): approve, reject or exclude.
 *
 * One DB::transaction(..., 3) per decision: lock the review and the membership, check they belong
 * to the route POD, are unresolved and still pending, then
 *   approve → re-validate the current best match (MatchQualification + no exclusion) and move
 *             pending → outer_circle,
 *   reject  → pending → rejected (optional business reason, stable fallback otherwise),
 *   exclude → create the (pod, user) exclusion idempotently AND pending → rejected,
 * always through PodStateTransitionService::apply() (one history row with actor and reason;
 * profile gate and workspace access stay its job), and record the review decision. Anything
 * failing rolls everything back. Notifications run after commit (AfterCommit). Never Inner Circle.
 */
class PodMatchReviewDecisionService
{
    private const ATTEMPTS = 3;

    // Canonical audit reasons stored in pod_user_state_histories (stable, untranslated, like other history reasons).
    public const APPROVED_REASON = 'Match approved by business';
    public const REJECTED_REASON = 'Match rejected by business';
    public const EXCLUDED_REASON = 'Excluded from future POD matching';

    public function __construct(
        private PodStateTransitionService $transitions,
        private PodMatchReviewService $reviews,
        private PodMatchingExclusionService $exclusions,
        private AfterCommit $afterCommit,
    ) {
    }

    /**
     * @throws MatchReviewConflict already decided / no longer pending (409)
     * @throws MatchReviewNotApprovable approval of a stale or excluded proposal (422)
     * @throws \App\Services\Membership\InvalidStateTransition e.g. incomplete employee profile (422)
     * @throws ModelNotFoundException the review does not belong to $pod (404)
     */
    public function decide(Pod $pod, PodMatchReview $review, MatchReviewAction $action, User $actor, ?string $reason = null): MembershipResult
    {
        $note = PodMatchingExclusionService::normalize($reason);

        $result = DB::transaction(function () use ($pod, $review, $action, $actor, $note) {
            $locked = PodMatchReview::query()->whereKey($review->id)->lockForUpdate()->first()
                ?? throw new MatchReviewConflict();
            $membership = PodUser::query()->whereKey($locked->pod_user_id)->lockForUpdate()->first()
                ?? throw new MatchReviewConflict();
            if ((int) $membership->pod_id !== (int) $pod->id) {
                throw (new ModelNotFoundException())->setModel(PodMatchReview::class, [$review->id]);
            }
            if ($locked->isResolved() || $membership->state !== State::PENDING_BUSINESS_APPROVAL) {
                throw new MatchReviewConflict();
            }
            $employee = User::query()->findOrFail($membership->user_id);

            [$target, $historyReason, $decision] = match ($action) {
                MatchReviewAction::APPROVE => [State::OUTER_CIRCLE, self::APPROVED_REASON, MatchReviewDecision::APPROVED],
                MatchReviewAction::REJECT => [State::REJECTED, $note ?? self::REJECTED_REASON, MatchReviewDecision::REJECTED],
                MatchReviewAction::EXCLUDE => [State::REJECTED, $note ? self::EXCLUDED_REASON . ': ' . $note : self::EXCLUDED_REASON, MatchReviewDecision::EXCLUDED],
            };

            if ($action === MatchReviewAction::APPROVE) {
                if ($this->exclusions->isExcluded($pod, $employee)) {
                    throw MatchReviewNotApprovable::excluded();
                }
                $best = $this->reviews->bestMatchFor($pod, (int) $employee->id);
                if ($best === null || !MatchQualification::of($best, $best->requirement, $pod)->qualified) {
                    throw MatchReviewNotApprovable::notQualified();
                }
            }
            if ($action === MatchReviewAction::EXCLUDE) {
                $this->exclusions->exclude($pod, $employee, $actor, $note);
            }

            // Review metadata first, so the transition hook (PodMatchReviewResolver) leaves it as decided here.
            $locked->forceFill([
                'resolved_at' => now(),
                'decision' => $decision->value,
                'decided_by' => $actor->id,
                'decision_reason' => $note,
            ])->save();

            $applied = $this->transitions->apply($membership, $target, $actor, $historyReason, [State::PENDING_BUSINESS_APPROVAL]);
            if (!$applied->applied) {
                throw new MatchReviewConflict(); // rolls back the review metadata and any exclusion
            }

            return $applied;
        }, self::ATTEMPTS);

        return $this->afterCommit->run($result);
    }
}

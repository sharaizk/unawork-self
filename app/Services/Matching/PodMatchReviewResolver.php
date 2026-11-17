<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchReviewDecision;
use App\Enums\PodUser\State;
use App\Models\PodMatchReview;
use App\Models\PodUser;
use App\Models\User;

/**
 * Keeps match-review metadata consistent with the membership (UNA-018). Called by
 * PodStateTransitionService::apply() inside its transaction whenever a membership leaves
 * pending_business_approval, so EVERY write path (review queue, generic moderation API, web
 * accept/decline, automatic advancement, removal) resolves an open review together with the
 * transition. A review already resolved by PodMatchReviewDecisionService is left untouched.
 */
class PodMatchReviewResolver
{
    public function onTransition(PodUser $membership, State $from, State $to, ?User $actor, ?string $reason): void
    {
        if ($from !== State::PENDING_BUSINESS_APPROVAL) {
            return;
        }

        $decision = match (true) {
            $to === State::OUTER_CIRCLE && $actor === null => MatchReviewDecision::AUTOMATICALLY_ADVANCED,
            $to === State::OUTER_CIRCLE => MatchReviewDecision::APPROVED,
            $to === State::REJECTED => MatchReviewDecision::REJECTED,
            default => MatchReviewDecision::OTHERWISE_RESOLVED,
        };

        PodMatchReview::query()->where('pod_user_id', $membership->id)->whereNull('resolved_at')->update([
            'resolved_at' => now(),
            'decision' => $decision->value,
            'decided_by' => $actor?->id,
            'decision_reason' => $reason,
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Enums\Matching;

/**
 * pod_match_reviews.decision (UNA-018): how a matching proposal left pending_business_approval.
 * Review metadata only; the authoritative decision is the pod_user_state_histories row.
 */
enum MatchReviewDecision: string
{
    case APPROVED = 'approved';                             // business placed the employee in the Outer Circle
    case REJECTED = 'rejected';                             // business rejected the proposal
    case EXCLUDED = 'excluded';                             // rejected and excluded from future matching for the POD
    case AUTOMATICALLY_ADVANCED = 'automatically_advanced'; // automatic mode moved it to the Outer Circle (actor = system)
    case OTHERWISE_RESOLVED = 'otherwise_resolved';         // any other legitimate transition (e.g. removal)

    public function label(): string
    {
        return match ($this) {
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
            self::EXCLUDED => __('Excluded from matching'),
            self::AUTOMATICALLY_ADVANCED => __('Placed automatically'),
            self::OTHERWISE_RESOLVED => __('Resolved'),
        };
    }
}

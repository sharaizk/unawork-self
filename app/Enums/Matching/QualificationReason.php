<?php

namespace App\Enums\Matching;

/** Why a persisted match is or is not qualified (UNA-014). See MatchQualification. */
enum QualificationReason: string
{
    case QUALIFIED = 'qualified';
    // Disqualified by a mandatory criterion, or not scorable: never overridden by a high score.
    case NOT_ELIGIBLE = 'not_eligible';
    case BELOW_THRESHOLD = 'below_threshold';
    case REQUIREMENT_INACTIVE = 'requirement_inactive';
    case POD_NOT_PUBLISHED = 'pod_not_published';

    public function label(): string
    {
        return match ($this) {
            self::QUALIFIED => __('Qualified'),
            self::NOT_ELIGIBLE => __('Not eligible: a mandatory criterion is not met or nothing could be scored.'),
            self::BELOW_THRESHOLD => __('Below the POD minimum match score.'),
            self::REQUIREMENT_INACTIVE => __('The worker requirement is not active.'),
            self::POD_NOT_PUBLISHED => __('The POD is not published yet.'),
        };
    }
}

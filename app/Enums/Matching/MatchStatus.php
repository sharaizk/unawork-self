<?php

namespace App\Enums\Matching;

/** pod_matches.status (UNA-013). Only ELIGIBLE is eligible; the score is kept in every case. */
enum MatchStatus: string
{
    // Every mandatory criterion passed and at least one weighted criterion was scored.
    case ELIGIBLE = 'eligible';
    // At least one mandatory criterion failed (see the reasons with disqualifying = true).
    case DISQUALIFIED = 'disqualified';
    // The requirement configures no weighted criterion, so there is nothing to score (score 0).
    case NOT_SCORABLE = 'not_scorable';

    public function isEligible(): bool
    {
        return $this === self::ELIGIBLE;
    }

    public function label(): string
    {
        return match ($this) {
            self::ELIGIBLE => __('Eligible'),
            self::DISQUALIFIED => __('Disqualified'),
            self::NOT_SCORABLE => __('Not scorable'),
        };
    }
}

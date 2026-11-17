<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchCriterion;
use App\Enums\Matching\MatchReasonCode;

/**
 * Immutable outcome of one criterion (UNA-013). Points are integers in hundredths of a point, so
 * stored values never depend on floating-point arithmetic.
 */
final class CriterionResult
{
    public function __construct(
        public readonly MatchCriterion $criterion,
        public readonly bool $active,
        public readonly int $possiblePoints,
        public readonly int $awardedHundredths,
        public readonly bool $passed,
        public readonly bool $mandatory,
        public readonly bool $disqualifying,
        public readonly MatchReasonCode $code,
        public readonly array $context = [],
    ) {
    }

    /** Awarded points as a fixed two-decimal string, e.g. "16.67". */
    public function awardedPoints(): string
    {
        return PodMatchScorer::formatHundredths($this->awardedHundredths);
    }
}

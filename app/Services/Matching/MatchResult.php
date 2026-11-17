<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchCriterion;
use App\Enums\Matching\MatchStatus;
use Carbon\CarbonImmutable;

/** Immutable result of scoring one employee against one POD worker requirement (UNA-013). */
final class MatchResult
{
    /**
     * @param CriterionResult[] $criteria every MatchCriterion, in declaration order
     * @param MatchCriterion[] $failedMandatory
     */
    public function __construct(
        public readonly int $scoreHundredths,
        public readonly MatchStatus $status,
        public readonly int $activeWeight,
        public readonly string $scoringVersion,
        public readonly CarbonImmutable $evaluatedAt,
        public readonly array $criteria,
        public readonly array $failedMandatory,
    ) {
    }

    /** Final 0-100 score as a fixed two-decimal string, e.g. "83.33". */
    public function score(): string
    {
        return PodMatchScorer::formatHundredths($this->scoreHundredths);
    }

    public function isEligible(): bool
    {
        return $this->status->isEligible();
    }

    public function isDisqualified(): bool
    {
        return $this->status === MatchStatus::DISQUALIFIED;
    }

    public function criterion(MatchCriterion $criterion): CriterionResult
    {
        foreach ($this->criteria as $result) {
            if ($result->criterion === $criterion) {
                return $result;
            }
        }

        throw new \LogicException("Missing criterion {$criterion->value}.");
    }
}

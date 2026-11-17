<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchStatus;
use App\Enums\Matching\QualificationReason;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodWorkerRequirement;

/**
 * The single qualification rule (UNA-014). A persisted match is qualified only when:
 *
 *   status = eligible  AND  score >= POD minimum_match_threshold
 *   AND the worker requirement is active  AND the POD is published.
 *
 * Equality qualifies. The comparison is in integer hundredths (score 69.99 < threshold 70). A high
 * score never overrides a failed mandatory criterion (neither the stored status nor, when the
 * reasons are loaded, any stored disqualifying reason). Changing the threshold never changes a score.
 */
final class MatchQualification
{
    private function __construct(
        public readonly bool $qualified,
        public readonly QualificationReason $reason,
        public readonly int $threshold,
        public readonly int $scoreHundredths,
    ) {
    }

    public static function of(PodMatch $match, ?PodWorkerRequirement $requirement = null, ?Pod $pod = null): self
    {
        $requirement ??= $match->requirement;
        $pod ??= $requirement->relationLoaded('pod') ? $requirement->pod : ($match->relationLoaded('pod') ? $match->pod : $requirement->pod);
        $threshold = $pod->minimumMatchThreshold();
        $score = self::hundredths($match->score);

        // UNA-016: a stored disqualifying reason wins even if an algorithm reported "eligible".
        $disqualified = $match->relationLoaded('reasons') && $match->reasons->contains(fn ($r) => (bool) $r->disqualifying);

        $reason = match (true) {
            $match->status !== MatchStatus::ELIGIBLE, $disqualified => QualificationReason::NOT_ELIGIBLE,
            !self::meetsThreshold($score, $threshold) => QualificationReason::BELOW_THRESHOLD,
            !$requirement->is_active => QualificationReason::REQUIREMENT_INACTIVE,
            !$pod->isPublished() => QualificationReason::POD_NOT_PUBLISHED,
            default => QualificationReason::QUALIFIED,
        };

        return new self($reason === QualificationReason::QUALIFIED, $reason, $threshold, $score);
    }

    /** score (hundredths) >= threshold (whole points); equality qualifies. */
    public static function meetsThreshold(int $scoreHundredths, int $threshold): bool
    {
        return $scoreHundredths >= $threshold * 100;
    }

    /** A stored decimal(5,2) score ("69.99") as integer hundredths (6999). */
    public static function hundredths(mixed $score): int
    {
        return (int) round(((float) $score) * 100);
    }
}

<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchCriterion;
use App\Enums\Matching\MatchStatus;
use App\Models\PodMatch;
use App\Models\PodMatchReason;
use Carbon\CarbonImmutable;

/**
 * Immutable result of matching one employee to one POD (UNA-015): every evaluated requirement
 * match (persisted, reasons loaded, ordered by requirement id) and the deterministic best one.
 *
 * Best-match ordering (DeterministicPodMatchingService::rank()): status eligible < disqualified <
 * not_scorable, then higher score, then lower pod_worker_requirement_id. The POD threshold
 * (UNA-014) is reported separately and never changes the score, the status or the selection.
 */
final class PodMatchingResult
{
    public const NO_ACTIVE_REQUIREMENTS = 'no_active_requirements';
    // UNA-016: a newer generation was already stored for at least one requirement; nothing older was written.
    public const STALE_GENERATION = 'stale_generation';

    /**
     * @param array<int, PodMatch> $matches keyed by pod_worker_requirement_id, ascending
     */
    public function __construct(
        public readonly int $podId,
        public readonly int $userId,
        public readonly CarbonImmutable $evaluatedAt,
        public readonly string $scoringVersion,
        public readonly array $matches,
        public readonly ?PodMatch $best,
        public readonly ?MatchQualification $bestQualification,
        public readonly ?string $reason = null,
        // UNA-016: the algorithm key (pod_matches.algorithm); scoringVersion is its version.
        public readonly string $algorithm = DeterministicPodMatchingService::KEY,
        // UNA-016: true when this generation was older than a stored one (callers must not act on it).
        public readonly bool $stale = false,
    ) {
    }

    public function hasMatches(): bool
    {
        return $this->matches !== [];
    }

    public function bestRequirementId(): ?int
    {
        return $this->best?->pod_worker_requirement_id !== null ? (int) $this->best->pod_worker_requirement_id : null;
    }

    /** Best raw score as a two-decimal string, e.g. "83.33"; null without matches. */
    public function bestScore(): ?string
    {
        return $this->best !== null ? (string) $this->best->score : null;
    }

    /** NOT_SCORABLE when the POD has no active requirement. */
    public function bestStatus(): MatchStatus
    {
        return $this->best?->status ?? MatchStatus::NOT_SCORABLE;
    }

    /** @return MatchCriterion[] failed mandatory criteria of the best match, in criterion order */
    public function bestFailedMandatory(): array
    {
        if ($this->best === null) {
            return [];
        }

        return $this->best->reasons->where('disqualifying', true)->sortBy('position')
            ->map(fn (PodMatchReason $r) => $r->criterion)->values()->all();
    }

    /**
     * Whether the best match is eligible and scores at least the POD's current minimum threshold.
     * Publication is not part of this check; see bestQualification for the full UNA-014 rule.
     */
    public function bestMeetsThreshold(): bool
    {
        return $this->best !== null
            && $this->best->status === MatchStatus::ELIGIBLE
            && MatchQualification::meetsThreshold($this->bestQualification->scoreHundredths, $this->bestQualification->threshold);
    }

    /** Full UNA-014 qualification of the best match (also needs a published POD). */
    public function bestIsQualified(): bool
    {
        return (bool) $this->bestQualification?->qualified;
    }
}

<?php

namespace App\Services\Matching;

use App\Models\PodMatch;
use App\Models\PodMatchReview;
use App\Models\User;

/**
 * One card of the business match-review queue (UNA-018), built by PodMatchReviewService. The
 * explanation is PodMatchPresenter output of the employee's CURRENT best match for the POD;
 * the review keeps its source match only for provenance.
 */
final class PodMatchReviewItem
{
    public const STALE_BELOW_THRESHOLD = 'below_threshold';
    public const STALE_NOT_ELIGIBLE = 'not_eligible';
    public const STALE_UNAVAILABLE = 'unavailable';

    /**
     * @param array<string, mixed>|null $presented PodMatchPresenter::present() of $match
     * @param array<int, array<string, mixed>> $highlights strongest passed criteria
     * @param array<string, string> $profile business-relevant profile facts (no contact data)
     */
    public function __construct(
        public readonly PodMatchReview $review,
        public readonly User $employee,
        public readonly ?PodMatch $match,
        public readonly ?MatchQualification $qualification,
        public readonly ?array $presented,
        public readonly array $highlights,
        public readonly ?string $headline,
        public readonly array $profile,
        public readonly ?string $avatarUrl,
    ) {
    }

    public function isQualified(): bool
    {
        return (bool) $this->qualification?->qualified;
    }

    /** Why approval is disabled, or null when the proposal can be approved. */
    public function staleReason(): ?string
    {
        if ($this->match === null) {
            return self::STALE_UNAVAILABLE;
        }
        if ($this->isQualified()) {
            return null;
        }

        return $this->qualification->reason === \App\Enums\Matching\QualificationReason::BELOW_THRESHOLD
            ? self::STALE_BELOW_THRESHOLD
            : self::STALE_NOT_ELIGIBLE;
    }

    public function scoreHundredths(): int
    {
        return $this->match ? MatchQualification::hundredths($this->match->score) : -1;
    }
}

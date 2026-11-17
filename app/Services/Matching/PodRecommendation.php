<?php

namespace App\Services\Matching;

use App\Enums\PodUser\State;
use App\Models\Pod;
use App\Models\PodMatch;

/**
 * One POD recommendation card (UNA-017): the POD, its best qualified requirement match and
 * PodMatchPresenter's translated explanation. Built by PodRecommendationService; Blade only
 * renders these values.
 */
final class PodRecommendation
{
    /**
     * @param array<string, mixed> $presented PodMatchPresenter::present() output
     * @param array<int, array<string, mixed>> $highlights strongest passed criteria (presenter entries)
     * @param array<string, string> $details work mode / engagement / location / project type labels
     */
    public function __construct(
        public readonly Pod $pod,
        public readonly PodMatch $match,
        public readonly array $presented,
        public readonly array $highlights,
        public readonly array $details,
        public readonly ?string $companyName,
        public readonly ?string $roleTitle,
        public readonly ?State $membershipState,
    ) {
    }

    /** No membership yet: the employee may apply. */
    public function canApply(): bool
    {
        return $this->membershipState === null;
    }

    public function scoreValue(): float
    {
        return (float) $this->presented['score'];
    }
}

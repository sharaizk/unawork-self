<?php

namespace App\Services\Matching;

use App\Models\PodMatch;

/** Outcome of PodMatchService::persistGeneration() (UNA-016). */
final class PersistedGeneration
{
    /**
     * @param array<int, PodMatch> $matches stored rows keyed by pod_worker_requirement_id (reasons loaded)
     * @param int[] $staleRequirementIds entries not written because a newer generation is stored
     */
    public function __construct(
        public readonly array $matches,
        public readonly array $staleRequirementIds,
    ) {
    }

    public function isStale(): bool
    {
        return $this->staleRequirementIds !== [];
    }
}

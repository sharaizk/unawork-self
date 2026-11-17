<?php

namespace App\Jobs\Matching;

use Carbon\CarbonImmutable;

/**
 * Queue settings shared by the UNA-016 matching jobs, all from config('pod_matching'): the
 * dedicated queue, bounded tries/backoff/timeout and the uniqueness window. Jobs carry only
 * scalar ids and an ISO-8601 generation timestamp, never models.
 */
trait MatchingJob
{
    public int $tries;
    public int $timeout;
    public int $uniqueFor;
    /** @var int[] */
    public array $backoff;

    /** ISO-8601, whole seconds; fixed when the job is dispatched and kept across retries. */
    public string $generatedAt;

    protected function configureMatchingJob(string $generatedAt): void
    {
        $this->generatedAt = CarbonImmutable::parse($generatedAt)->startOfSecond()->toIso8601String();
        $this->onQueue((string) config('pod_matching.queue', 'matching'));
        $this->tries = max(1, (int) config('pod_matching.jobs.tries', 5));
        $this->timeout = max(1, (int) config('pod_matching.jobs.timeout', 80));
        $this->uniqueFor = max(1, (int) config('pod_matching.jobs.unique_for', 3600));
        $this->backoff = array_map('intval', (array) config('pod_matching.jobs.backoff', [30, 120, 300]));
    }

    public function generatedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->generatedAt);
    }

    protected function chunkSize(): int
    {
        return max(1, (int) config('pod_matching.jobs.coordinator_chunk_size', 200));
    }
}

<?php

namespace App\Jobs\Matching;

use App\Contracts\PodMatchingAlgorithm;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\User;
use App\Services\Matching\PodMatchingWorkflowService;
use App\Services\Profile\ProfileCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The atomic, retryable unit of match generation (UNA-016): one employee x one POD.
 *
 *  1. load both by id; deleted, unpublished, non-employee or ineligible → expected no-op
 *     (an ineligible profile's stored matches for this POD are deleted, memberships untouched),
 *  2. run the configured PodMatchingAlgorithm once for the whole POD with this job's generation
 *     timestamp (the same on every retry); it persists every active requirement result atomically,
 *  3. if that generation was stale (a newer one is stored), stop without acting,
 *  4. hand the stored best match to PodMatchingWorkflowService::applyPersisted(), which re-checks
 *     qualification from the stored row and applies the UNA-014 mode through
 *     PodStateTransitionService (Outer Circle at most, never Inner Circle). Nothing is re-scored.
 *
 * Unexpected exceptions escape so Laravel retries (tries/backoff) and finally records the job in
 * failed_jobs. Every step is idempotent, so a retry after persistence simply re-writes the same
 * generation and finishes the membership step.
 */
class GenerateEmployeePodMatch implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, MatchingJob;

    public function __construct(public readonly int $userId, public readonly int $podId, string $generatedAt)
    {
        $this->configureMatchingJob($generatedAt);
    }

    public function uniqueId(): string
    {
        return "matching:employee-pod:{$this->userId}:{$this->podId}";
    }

    /** @return string what happened (for logs and tests) */
    public function handle(PodMatchingAlgorithm $algorithm, PodMatchingWorkflowService $workflow, ProfileCompletionService $completion): string
    {
        $user = User::query()->find($this->userId);
        $pod = Pod::query()->find($this->podId);
        if ($user === null || $pod === null) {
            return 'skipped_missing';
        }
        if (!$pod->isPublished()) {
            return 'skipped_pod_not_published';
        }
        if (!$user->isRegularUser() || !$user->isEmployee()) {
            return 'skipped_not_employee';
        }
        if (!$user->profile()->exists() || !$completion->isEligible($user)) {
            PodMatch::query()->forPodAndUser($pod->id, $user->id)->delete();
            return 'invalidated_incomplete_profile';
        }

        // UNA-018: an excluded pair is not generated (the workflow refuses it again under its lock).
        if (app(\App\Services\Matching\PodMatchingExclusionService::class)->isExcluded($pod, $user)) {
            return 'skipped_excluded';
        }

        $result = $algorithm->match($pod, $user, $this->generatedAt());
        if ($result->stale) {
            return 'stale';
        }
        if ($result->best === null) {
            return 'no_match';
        }

        // The stored best match, re-qualified by the workflow (status, reasons, threshold, publication).
        $outcome = $workflow->applyPersisted($result->best);

        return 'applied:' . $outcome->action->value;
    }
}

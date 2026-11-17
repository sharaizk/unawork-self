<?php

namespace App\Jobs\Matching;

use App\Models\Pod;
use App\Models\User;
use App\Services\Profile\ProfileCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * POD coordinator (UNA-016): after a published POD's matching configuration changes (or it is
 * published), fan out one GenerateEmployeePodMatch per employee whose saved profile meets the
 * matching minimum (ProfileCompletionService), in user id order and chunks. Scores nothing.
 *
 * No lossy pre-filter (e.g. "holds a required skill"): candidates may score through any criterion.
 * Expected no-ops: the POD is gone, not published, or has no active requirement.
 */
class GenerateMatchesForPod implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, MatchingJob;

    public function __construct(public readonly int $podId, string $generatedAt)
    {
        $this->configureMatchingJob($generatedAt);
    }

    public function uniqueId(): string
    {
        return "matching:pod:{$this->podId}";
    }

    /** @return string what happened (for logs and tests) */
    public function handle(ProfileCompletionService $completion): string
    {
        $pod = Pod::query()->find($this->podId);
        if ($pod === null || !$pod->isPublished()) {
            return 'skipped_pod_unavailable';
        }
        if (!$pod->workerRequirements()->where('is_active', true)->exists()) {
            return 'skipped_no_active_requirements';
        }

        $dispatched = 0;
        User::query()->employees()
            ->whereHas('profile')
            // UNA-018: employees excluded from this POD's matching are never discovered.
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pod_matching_exclusions as pme')
                ->whereColumn('pme.user_id', 'users.id')->where('pme.pod_id', $this->podId))
            ->with(array_merge(['roles'], ProfileCompletionService::RELATIONS))
            ->chunkById($this->chunkSize(), function ($users) use ($completion, &$dispatched) {
                foreach ($users as $user) {
                    if ($user->isEmployee() && $completion->isEligible($user)) {
                        GenerateEmployeePodMatch::dispatch((int) $user->id, $this->podId, $this->generatedAt);
                        $dispatched++;
                    }
                }
            });

        return "dispatched:{$dispatched}";
    }
}

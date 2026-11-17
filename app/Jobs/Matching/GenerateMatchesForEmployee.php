<?php

namespace App\Jobs\Matching;

use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\User;
use App\Services\Profile\ProfileCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Employee coordinator (UNA-016): after an employee's matching-relevant profile change, fan out
 * one GenerateEmployeePodMatch per published POD that has an active worker requirement, in POD id
 * order and chunks. Scores nothing.
 *
 * Expected no-ops: the user is gone or is not an employee. When the profile is not eligible for
 * matching any more, the employee's stored match recommendations are deleted; memberships,
 * workspace access and state history are never touched.
 */
class GenerateMatchesForEmployee implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, MatchingJob;

    public function __construct(public readonly int $userId, string $generatedAt)
    {
        $this->configureMatchingJob($generatedAt);
    }

    public function uniqueId(): string
    {
        return "matching:employee:{$this->userId}";
    }

    /** @return string what happened (for logs and tests) */
    public function handle(ProfileCompletionService $completion): string
    {
        $user = User::query()->find($this->userId);
        if ($user === null || !$user->isRegularUser() || !$user->isEmployee()) {
            return 'skipped_not_employee';
        }
        if (!$user->profile()->exists() || !$completion->isEligible($user)) {
            PodMatch::query()->where('user_id', $user->id)->delete(); // reasons cascade
            return 'invalidated_incomplete_profile';
        }

        $dispatched = 0;
        Pod::published()
            ->whereHas('workerRequirements', fn ($q) => $q->where('is_active', true))
            ->select('pods.id')
            ->chunkById($this->chunkSize(), function ($pods) use (&$dispatched) {
                foreach ($pods as $pod) {
                    GenerateEmployeePodMatch::dispatch($this->userId, (int) $pod->id, $this->generatedAt);
                    $dispatched++;
                }
            }, 'pods.id', 'id');

        return "dispatched:{$dispatched}";
    }
}

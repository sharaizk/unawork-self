<?php

namespace App\Services\Matching;

use App\Jobs\Matching\GenerateMatchesForEmployee;
use App\Jobs\Matching\GenerateMatchesForPod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The only place request-time code enqueues match generation (UNA-016).
 *
 * Each call registers the dispatch with DB::afterCommit(): it happens once the surrounding
 * transaction (and any enclosing one) has committed, never after a rollback, and only for the
 * attempt that committed. The generation timestamp is taken at that moment (after the data is
 * durable). An enqueue failure is reported and swallowed: the business data is already committed
 * and must not look failed. Nothing here scores, and nothing here touches onboarding state.
 */
class MatchGenerationDispatcher
{
    public function employeeAfterCommit(int $userId): void
    {
        $this->afterCommit(fn () => GenerateMatchesForEmployee::dispatch($userId, self::now()));
    }

    public function podAfterCommit(int $podId): void
    {
        $this->afterCommit(fn () => GenerateMatchesForPod::dispatch($podId, self::now()));
    }

    /** Generation timestamps are whole seconds, the precision of pod_matches.evaluated_at. */
    public static function now(): string
    {
        return CarbonImmutable::now()->startOfSecond()->toIso8601String();
    }

    private function afterCommit(\Closure $dispatch): void
    {
        DB::afterCommit(function () use ($dispatch) {
            try {
                // PendingDispatch enqueues (and checks job uniqueness) when destroyed: keep that inside the try.
                $pending = $dispatch();
                unset($pending);
            } catch (Throwable $e) {
                report($e); // e.g. queue connection down: the committed change stays as it is
            }
        });
    }
}

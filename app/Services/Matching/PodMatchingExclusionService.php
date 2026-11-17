<?php

namespace App\Services\Matching;

use App\Models\Pod;
use App\Models\PodMatchingExclusion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Employee x POD exclusions from automated matching (UNA-018). Enforced by the pair job, both
 * coordinators, PodMatchingWorkflowService (inside its membership transaction), the
 * recommendation query and the review queue. Not a POD ban: manual discovery and applications
 * follow their own rules, and no match, reason or membership is deleted.
 */
class PodMatchingExclusionService
{
    public function isExcluded(Pod|int $pod, User|int $employee): bool
    {
        return PodMatchingExclusion::query()
            ->where('pod_id', $pod instanceof Pod ? $pod->id : $pod)
            ->where('user_id', $employee instanceof User ? $employee->id : $employee)
            ->exists();
    }

    /**
     * Idempotent (unique pod/user pair + insertOrIgnore): a repeated exclusion keeps the first row.
     * Joins the caller's transaction when there is one.
     */
    public function exclude(Pod $pod, User $employee, ?User $actor, ?string $reason = null): PodMatchingExclusion
    {
        $now = now();
        DB::table('pod_matching_exclusions')->insertOrIgnore([
            'pod_id' => $pod->id,
            'user_id' => $employee->id,
            'excluded_by' => $actor?->id,
            'reason' => self::normalize($reason),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return PodMatchingExclusion::query()->where('pod_id', $pod->id)->where('user_id', $employee->id)->firstOrFail();
    }

    public static function normalize(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : mb_substr($reason, 0, 2000);
    }
}

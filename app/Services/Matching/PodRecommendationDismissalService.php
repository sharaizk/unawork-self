<?php

namespace App\Services\Matching;

use App\Models\Pod;
use App\Models\PodRecommendationDismissal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hides a POD from one employee's recommendation screen (UNA-017).
 *
 * The employee is always the authenticated account passed in by the caller (never request
 * input), so nobody can dismiss for someone else. Idempotent: the unique (user_id, pod_id) pair
 * plus insertOrIgnore make a repeated or concurrent dismissal a no-op. Never touches pod_matches,
 * their reasons or memberships, and never hides the POD from manual discovery.
 */
class PodRecommendationDismissalService
{
    public function __construct(private PodRecommendationService $recommendations)
    {
    }

    /** @return bool true when this call created the dismissal, false when it already existed */
    public function dismiss(User $employee, Pod $pod): bool
    {
        if (!$this->recommendations->canView($employee)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(__('Only employees can manage POD recommendations.'));
        }

        $now = now();

        return DB::table('pod_recommendation_dismissals')->insertOrIgnore([
            'user_id' => $employee->id,
            'pod_id' => $pod->id,
            'dismissed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }

    public function isDismissed(User $employee, Pod $pod): bool
    {
        return PodRecommendationDismissal::query()->where('user_id', $employee->id)->where('pod_id', $pod->id)->exists();
    }
}

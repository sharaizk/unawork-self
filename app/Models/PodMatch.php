<?php

namespace App\Models;

use App\Enums\Matching\MatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A persisted match of one employee against one POD worker requirement (UNA-013). Written only by
 * App\Services\Matching\PodMatchService; nothing here is mass assignable. Reasons are ordered by
 * criterion position. A match never changes POD membership.
 */
class PodMatch extends Model
{
    protected $table = 'pod_matches';

    protected $guarded = ['*'];

    protected $casts = [
        'score' => 'decimal:2',
        'status' => MatchStatus::class,
        'active_weight' => 'integer',
        'evaluated_at' => 'datetime',
    ];

    public function pod()
    {
        return $this->belongsTo(Pod::class);
    }

    public function requirement()
    {
        return $this->belongsTo(PodWorkerRequirement::class, 'pod_worker_requirement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reasons()
    {
        return $this->hasMany(PodMatchReason::class)->orderBy('position')->orderBy('id');
    }

    public function isEligible(): bool
    {
        return $this->status === MatchStatus::ELIGIBLE;
    }

    /** Matches of one candidate within one POD (one per requirement), UNA-015. */
    public function scopeForPodAndUser(Builder $query, int $podId, int $userId): Builder
    {
        return $query->where($query->qualifyColumn('pod_id'), $podId)->where($query->qualifyColumn('user_id'), $userId);
    }

    public function scopeEligible(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), MatchStatus::ELIGIBLE->value);
    }
}

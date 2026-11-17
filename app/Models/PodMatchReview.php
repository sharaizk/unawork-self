<?php

namespace App\Models;

use App\Enums\Matching\MatchReviewDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provenance + review metadata of a matching proposal (UNA-018). Created only by
 * PodMatchingWorkflowService, resolved by PodMatchReviewDecisionService or, for any other
 * transition out of pending_business_approval, by PodStateTransitionService. Not mass assignable.
 */
class PodMatchReview extends Model
{
    protected $table = 'pod_match_reviews';

    protected $guarded = ['*'];

    protected $casts = [
        'decision' => MatchReviewDecision::class,
        'resolved_at' => 'datetime',
    ];

    public function membership()
    {
        return $this->belongsTo(PodUser::class, 'pod_user_id');
    }

    public function sourceMatch()
    {
        return $this->belongsTo(PodMatch::class, 'source_pod_match_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('resolved_at'));
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}

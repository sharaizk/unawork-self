<?php

namespace App\Models;

use App\Enums\Matching\MatchCriterion;
use App\Enums\Matching\MatchReasonCode;
use Illuminate\Database\Eloquent\Model;

/**
 * One criterion of a persisted match (UNA-013): stable criterion key + reason code + structured
 * context. Display text comes from App\Services\Matching\PodMatchPresenter, never from the database.
 */
class PodMatchReason extends Model
{
    protected $table = 'pod_match_reasons';

    protected $guarded = ['*'];

    protected $casts = [
        'criterion' => MatchCriterion::class,
        'code' => MatchReasonCode::class,
        'position' => 'integer',
        'active' => 'boolean',
        'possible_points' => 'integer',
        'awarded_points' => 'decimal:2',
        'passed' => 'boolean',
        'mandatory' => 'boolean',
        'disqualifying' => 'boolean',
        'context' => 'array',
    ];

    public function match()
    {
        return $this->belongsTo(PodMatch::class, 'pod_match_id');
    }
}

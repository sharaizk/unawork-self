<?php

namespace App\Models;

use App\Enums\PodUser\State;
use App\Models\Concerns\IsUniqueMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's membership of a POD. `state` (App\Enums\PodUser\State) is the only workflow/circle
 * source and is deliberately not mass assignable: change it through PodStateTransitionService.
 */
class PodUser extends Model
{
    use HasFactory;
    use IsUniqueMembership;

    public static function membershipParentKey(): string
    {
        return 'pod_id';
    }

    protected $fillable = [
        'user_id',
        'pod_id',
        'permission',
        'reason',
    ];

    protected $casts = [
        'state' => State::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pod()
    {
        return $this->belongsTo(Pod::class);
    }

    /** Chronological, append-only history of this membership's states. */
    public function stateHistory()
    {
        return $this->hasMany(PodUserStateHistory::class, 'pod_user_id')->orderBy('id');
    }

    public function isOwner(): bool
    {
        return $this->permission === 'Owner';
    }

    // ---- state scopes ---------------------------------------------------------------

    /** Everything except removed memberships. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('state', '!=', State::REMOVED->value);
    }

    public function scopeApplicants(Builder $query): Builder
    {
        return $query->whereIn('state', State::values(State::applicants()));
    }

    public function scopeActiveMembers(Builder $query): Builder
    {
        return $query->whereIn('state', State::values(State::activeMembers()));
    }

    public function scopeOuterCircle(Builder $query): Builder
    {
        return $query->whereIn('state', State::values(State::outerCircle()));
    }

    public function scopeInnerCircle(Builder $query): Builder
    {
        return $query->where('state', State::INNER_CIRCLE->value);
    }

    /** Matching provenance of this membership, when the matching workflow proposed it (UNA-018). */
    public function matchReview()
    {
        return $this->hasOne(PodMatchReview::class, 'pod_user_id');
    }
}

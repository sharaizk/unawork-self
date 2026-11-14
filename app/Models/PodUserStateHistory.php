<?php

namespace App\Models;

use App\Enums\PodUser\State;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit row: one per POD membership state change (UNA-006).
 * Written only by PodStateTransitionService; never updated or deleted by application code.
 */
class PodUserStateHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pod_user_state_histories';

    protected $guarded = ['*'];

    protected $casts = [
        'from_state' => State::class,
        'to_state' => State::class,
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('POD state history is append-only.'));
        static::deleting(fn () => throw new \LogicException('POD state history is append-only.'));
    }

    public function membership()
    {
        return $this->belongsTo(PodUser::class, 'pod_user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An employee hid a POD from their recommendations (UNA-017). Written only by
 * App\Services\Matching\PodRecommendationDismissalService; nothing is mass assignable.
 */
class PodRecommendationDismissal extends Model
{
    protected $table = 'pod_recommendation_dismissals';

    protected $guarded = ['*'];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pod()
    {
        return $this->belongsTo(Pod::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An employee excluded from automated matching for one POD (UNA-018). Written by PodMatchingExclusionService. */
class PodMatchingExclusion extends Model
{
    protected $table = 'pod_matching_exclusions';

    protected $guarded = ['*'];

    public function pod()
    {
        return $this->belongsTo(Pod::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function excludedBy()
    {
        return $this->belongsTo(User::class, 'excluded_by');
    }
}

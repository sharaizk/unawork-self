<?php

namespace App\Models;

use App\Enums\Profile\EngagementType;
use Illuminate\Database\Eloquent\Model;

class ProfileExperience extends Model
{
    protected $table = 'profile_experiences';

    // profile_id is never mass assigned: records are created through the owning profile relation.
    protected $fillable = [
        'employer', 'job_title', 'job_title_id', 'engagement_type', 'location',
        'start_date', 'end_date', 'is_current', 'description',
    ];

    protected $casts = [
        'engagement_type' => EngagementType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }
}

<?php

namespace App\Models;

use App\Enums\Profile\AvailabilityStatus;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\WorkMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An employee's profile. Repeatable professional data lives in related tables (UNA-007); the
 * completion score is calculated by App\Services\Profile\ProfileCompletionService and never stored.
 * `website` is the personal website.
 */
class Profile extends Model
{
    use HasFactory;

    /** Relations ProfileCompletionService and the profile page need, for eager loading. */
    public const DETAIL_RELATIONS = [
        'experiences', 'educations', 'certifications', 'portfolios', 'preferredRoles', 'projectTypes',
    ];

    protected $fillable = [
        'user_id',
        'country',
        'state',
        'city',
        'zip_code',
        'address',
        'phone_number',
        'cv_file',
        'about',
        'job_title_id',
        'industry_id',
        'website',
        'years_experience',
        'linkedin_url',
        'github_url',
        'timezone',
        'availability_status',
        'available_from',
        'hours_per_week',
        'preferred_work_mode',
        'preferred_engagement_type',
        'open_to_relocation',
        'willing_to_travel',
    ];

    protected $casts = [
        'years_experience' => 'decimal:1',
        'hours_per_week' => 'integer',
        'available_from' => 'date',
        'availability_status' => AvailabilityStatus::class,
        'preferred_work_mode' => WorkMode::class,
        'preferred_engagement_type' => EngagementType::class,
        'open_to_relocation' => 'boolean',
        'willing_to_travel' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    /** The employee's primary industry; narrows the skill and role pickers on /my-profile. */
    public function industry()
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    public function experiences()
    {
        return $this->hasMany(ProfileExperience::class)->orderByDesc('is_current')->orderByDesc('start_date')->orderBy('id');
    }

    public function educations()
    {
        return $this->hasMany(ProfileEducation::class)->orderByDesc('end_year')->orderBy('id');
    }

    public function certifications()
    {
        return $this->hasMany(ProfileCertification::class)->orderByDesc('issue_date')->orderBy('id');
    }

    public function portfolios()
    {
        return $this->hasMany(ProfilePortfolio::class)->orderBy('id');
    }

    public function preferredRoles()
    {
        return $this->hasMany(ProfilePreferredRole::class)->orderBy('id');
    }

    public function preferredJobTitles()
    {
        return $this->belongsToMany(JobTitle::class, 'profile_preferred_roles')->withTimestamps();
    }

    public function projectTypes()
    {
        return $this->hasMany(ProfileProjectType::class)->orderBy('id');
    }
}

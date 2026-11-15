<?php

namespace App\Models;

use App\Enums\Profile\EngagementType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use Illuminate\Database\Eloquent\Model;

/**
 * A company's structured worker requirement (UNA-010): one professional role, one or more skills,
 * headcount and preferences. workspace_id is never mass assigned; requirements are created through
 * the owning workspace by BusinessOnboardingService.
 */
class WorkspaceWorkerRequirement extends Model
{
    protected $table = 'workspace_worker_requirements';

    protected $fillable = [
        'job_title_id', 'headcount', 'minimum_years_experience', 'minimum_skill_proficiency',
        'preferred_work_mode', 'preferred_engagement_type', 'location', 'description', 'is_active',
    ];

    protected $casts = [
        'headcount' => 'integer',
        'minimum_years_experience' => 'decimal:1',
        'minimum_skill_proficiency' => SkillProficiency::class,
        'preferred_work_mode' => WorkMode::class,
        'preferred_engagement_type' => EngagementType::class,
        'is_active' => 'boolean',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'workspace_worker_requirement_skill', 'workspace_worker_requirement_id', 'skill_id')
            ->withTimestamps();
    }

    /** Complete enough to count towards onboarding (see BusinessOnboardingCompletionService). */
    public function isComplete(): bool
    {
        return $this->is_active
            && $this->job_title_id !== null
            && (int) $this->headcount >= 1
            && $this->skills->isNotEmpty()
            && $this->preferred_work_mode !== null
            && $this->preferred_engagement_type !== null;
    }
}

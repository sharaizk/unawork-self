<?php

namespace App\Models;

use App\Enums\Profile\EngagementType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use Illuminate\Database\Eloquent\Model;

/**
 * A POD-owned worker requirement (UNA-011). Imported company templates are copied into these rows
 * (source_workspace_requirement_id only records the origin), so template edits never change a POD.
 * pod_id is never mass assigned.
 */
class PodWorkerRequirement extends Model
{
    protected $table = 'pod_worker_requirements';

    protected $fillable = [
        'source_workspace_requirement_id', 'job_title_id', 'headcount', 'minimum_years_experience',
        'minimum_skill_proficiency', 'preferred_work_mode', 'preferred_engagement_type', 'location', 'description', 'is_active',
    ];

    protected $casts = [
        'headcount' => 'integer',
        'minimum_years_experience' => 'decimal:1',
        'minimum_skill_proficiency' => SkillProficiency::class,
        'preferred_work_mode' => WorkMode::class,
        'preferred_engagement_type' => EngagementType::class,
        'is_active' => 'boolean',
    ];

    public function pod()
    {
        return $this->belongsTo(Pod::class);
    }

    public function sourceRequirement()
    {
        return $this->belongsTo(WorkspaceWorkerRequirement::class, 'source_workspace_requirement_id');
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'pod_worker_requirement_skill', 'pod_worker_requirement_id', 'skill_id')->withTimestamps();
    }

    /** Complete enough to count towards POD configuration (see PodConfigurationService). */
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

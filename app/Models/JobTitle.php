<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];
    
    /** "Professional Roles" in the admin UI; never an authorization role. */
    use \App\Models\Concerns\IsCatalogEntry;

    public static function catalogColumn(): string
    {
        return 'title';
    }

    /** Current job titles, preferred roles and employment-experience records (UNA-007). */
    public static function usageRelations(): array
    {
        return ['profiles', 'preferredRoles', 'experiences', 'workerRequirements', 'podWorkerRequirements'];
    }

    /** POD worker requirements for this role (UNA-011). */
    public function podWorkerRequirements()
    {
        return $this->hasMany(PodWorkerRequirement::class, 'job_title_id');
    }

    /** Company worker requirements for this role (UNA-010). */
    public function workerRequirements()
    {
        return $this->hasMany(WorkspaceWorkerRequirement::class, 'job_title_id');
    }

    public static function tagRelations(): array
    {
        return ['industries'];
    }

    /** Industries this role belongs to (none = a general role offered for every industry). */
    public function industries()
    {
        return $this->belongsToMany(Industry::class, 'industry_job_title')->withTimestamps();
    }

    /** Profiles using this as their current job title. */
    public function profiles()
    {
        return $this->hasMany(Profile::class, 'job_title_id');
    }

    public function preferredRoles()
    {
        return $this->hasMany(ProfilePreferredRole::class, 'job_title_id');
    }

    public function experiences()
    {
        return $this->hasMany(ProfileExperience::class, 'job_title_id');
    }
}

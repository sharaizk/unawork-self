<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;
    use \App\Models\Concerns\IsCatalogEntry;

    public static function catalogColumn(): string
    {
        return 'name';
    }

    /** Employee skill assignments, POD skills and job-vacancy requirements. */
    public static function usageRelations(): array
    {
        return ['users', 'pods', 'jobVacancies'];
    }

    public static function tagRelations(): array
    {
        return ['industries'];
    }

    /** Industries this skill belongs to (none = a general skill offered for every industry). */
    public function industries()
    {
        return $this->belongsToMany(Industry::class, 'industry_skill')->withTimestamps();
    }

    protected $fillable = ['name'];

    //get users who has a skill from skillObj
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_skills');
    }

    public function pods()
    {
        return $this->belongsToMany(Pod::class, 'pods_skills', 'skill_id', 'pod_id');
    }

    public function jobVacancies()
    {
        return $this->belongsToMany(JobVacancy::class, 'job_vacancy_skill');
    }
}

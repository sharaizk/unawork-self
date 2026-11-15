<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    use HasFactory;
    use \App\Models\Concerns\IsCatalogEntry;

    public static function catalogColumn(): string
    {
        return 'name';
    }

    /** Real usage: company workspaces and employee profiles classified with this industry. */
    public static function usageRelations(): array
    {
        return ['workspaces', 'profiles'];
    }

    /**
     * Skills and professional roles linked to this industry. Classification, not usage: shown
     * separately in the admin list, and removed (the skills/roles themselves stay) on delete.
     */
    public static function tagRelations(): array
    {
        return ['skills', 'jobTitles'];
    }

    public function profiles()
    {
        return $this->hasMany(Profile::class, 'industry_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'industry_skill')->withTimestamps();
    }

    /** Professional roles (job titles) used in this industry. */
    public function jobTitles()
    {
        return $this->belongsToMany(JobTitle::class, 'industry_job_title')->withTimestamps();
    }

    protected $fillable = [
        'name',
    ];

    public function workspaces()
    {
        return $this->hasMany('App\Models\Workspace', 'industry_id');
    }
}

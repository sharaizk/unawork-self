<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pod extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'image', 'description', 'created_by'
    ];

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'pods_skills', 'pod_id', 'skill_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'pod_users')
                    ->withPivot('permission', 'status', 'reason')
                    ->withTimestamps();
    }

    public function news()
    {
        return $this->morphMany(News::class, 'newsable');
    }

    public function workspaces()
    {
        return $this->morphMany(Workspace::class, 'ownerable');
    }

    public function projects()
    {
        return $this->hasManyThrough(Project::class, Workspace::class, 'ownerable_id', 'workspace')
                    ->where('workspaces.ownerable_type', Pod::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pod extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'image', 'description', 'created_by', 'inner_enabled'
    ];

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'pods_skills', 'pod_id', 'skill_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Current memberships (every state except removed); the pivot carries `state`. */
    public function users()
    {
        return $this->belongsToMany(User::class, 'pod_users')
                    ->withPivot('id', 'permission', 'state', 'reason')
                    ->wherePivot('state', '!=', \App\Enums\PodUser\State::REMOVED->value)
                    ->withTimestamps();
    }

    /** Outer and Inner Circle members (incl. screening), e.g. for public member lists. */
    public function activeUsers()
    {
        return $this->belongsToMany(User::class, 'pod_users')
                    ->withPivot('id', 'permission', 'state', 'reason')
                    ->wherePivotIn('state', \App\Enums\PodUser\State::values(\App\Enums\PodUser\State::activeMembers()))
                    ->withTimestamps();
    }

    /** Every membership row of this POD, including removed ones (management/history). */
    public function memberships()
    {
        return $this->hasMany(PodUser::class);
    }

    public function news()
    {
        return $this->morphMany(News::class, 'newsable');
    }

    public function workspace()
    {
        return $this->morphOne(Workspace::class, 'ownerable');
    }

    public function projects()
    {
        return $this->hasManyThrough(Project::class, Workspace::class, 'ownerable_id', 'workspace')
                    ->where('workspaces.ownerable_type', Pod::class);
    }

    public function currentUserPivot()
    {
        return $this->users()->where('user_id', \Auth::id())->first()?->pivot;
    }
}

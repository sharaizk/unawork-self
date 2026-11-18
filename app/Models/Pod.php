<?php

namespace App\Models;

use App\Enums\Pod\PodMatchingMode;
use App\Enums\Pod\PodStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A POD. UNA-011: `status` is draft until PodService::publish(); only published PODs are public
 * (use the published() scope). status, published_at and the configuration timestamps are never
 * mass assigned.
 */
class Pod extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'image', 'description', 'created_by', 'inner_enabled', 'matching_mode',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [
        'status' => PodStatus::class,
        'matching_mode' => PodMatchingMode::class,
        'minimum_match_threshold' => 'integer',
        'published_at' => 'datetime',
        'configuration_started_at' => 'datetime',
        'configuration_completed_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodStatus::PUBLISHED->value);
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PodStatus::DRAFT->value);
    }

    public function isPublished(): bool
    {
        return $this->status === PodStatus::PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === PodStatus::DRAFT;
    }

    /**
     * UNA-014: minimum match score (0-100) for a match to qualify. Never mass assigned; written by
     * PodService only. Falls back to the config default for an unsaved model.
     */
    public function minimumMatchThreshold(): int
    {
        return (int) ($this->minimum_match_threshold ?? config('pod_matching.default_minimum_threshold', 70));
    }

    /** Persisted match snapshots of this POD's requirements (UNA-013). */
    public function matches()
    {
        return $this->hasMany(PodMatch::class);
    }

    /** POD-owned worker requirements (UNA-011). */
    public function workerRequirements()
    {
        return $this->hasMany(PodWorkerRequirement::class)->orderBy('id');
    }

    /** POD Knowledge Base documents (UNA-020). */
    public function documents()
    {
        return $this->hasMany(PodDocument::class)->orderBy('id', 'desc');
    }

    /** Published POD Knowledge Base documents (UNA-020). */
    public function publishedDocuments()
    {
        return $this->hasMany(PodDocument::class)->published()->orderBy('id', 'desc');
    }

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

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
        return $this->belongsToMany(User::class, 'user_pods')
                    ->using(PodUser::class)
                    ->withPivot('permission', 'status', 'reason')
                    ->withTimestamps();
    }
}

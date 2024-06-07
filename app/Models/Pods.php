<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pods extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'image', 'description'
    ];

    public function skills()
    {
        return $this->belongsToMany(Skills::class, 'pods_skills', 'pod_id', 'skill_id');
    }
}

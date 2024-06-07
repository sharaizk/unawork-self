<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodsSkills extends Model
{
    use HasFactory;

    protected $fillable = [
        'pod_id', 'skill_id'
    ];

    public function pod()
    {
        return $this->belongsTo(Pod::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}

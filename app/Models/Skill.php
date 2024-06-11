<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

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
}

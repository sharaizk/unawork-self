<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobVacancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'salary',
    ];

    /**
     * Визначення зв'язку з моделлю User.
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'job_vacancy_skill');
    }
        
}
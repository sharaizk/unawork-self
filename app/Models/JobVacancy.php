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

    //salary accessor to remove .00 
    public function getSalaryAttribute($value)
    {
        return (int) $value;
    }

    public function getTotalApplicationsCount()
    {
        return $this->jobApplications()->count();
    }

    public function getPendingApplicationsCount()
    {
        return $this->jobApplications()->where('status', 'pending')->count();
    }
        
}
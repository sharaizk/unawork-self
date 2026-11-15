<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilePreferredRole extends Model
{
    protected $table = 'profile_preferred_roles';

    protected $fillable = ['job_title_id'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }
}

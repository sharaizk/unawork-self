<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileEducation extends Model
{
    protected $table = 'profile_educations';

    protected $fillable = ['institution', 'degree', 'field_of_study', 'start_year', 'end_year', 'description'];

    protected $casts = [
        'start_year' => 'integer',
        'end_year' => 'integer',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}

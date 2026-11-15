<?php

namespace App\Models;

use App\Enums\Profile\ProjectType;
use Illuminate\Database\Eloquent\Model;

class ProfileProjectType extends Model
{
    protected $table = 'profile_project_types';

    protected $fillable = ['project_type'];

    protected $casts = [
        'project_type' => ProjectType::class,
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}

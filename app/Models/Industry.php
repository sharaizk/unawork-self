<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    use HasFactory;

    public function workspaces()
    {
        return $this->hasMany('App\Models\Workspace', 'industry_id');
    }
}

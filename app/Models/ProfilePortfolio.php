<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilePortfolio extends Model
{
    protected $table = 'profile_portfolios';

    protected $fillable = ['title', 'url', 'description'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}

<?php

namespace App\Models\CmgmyrMessenger;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    use HasFactory;

    protected $table = 'threads';

    protected $fillable = ['subject', 'slug'];
}

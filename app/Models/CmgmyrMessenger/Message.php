<?php

namespace App\Models\CmgmyrMessenger;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['thread_id', 'user_id', 'body', 'attachment'];

    protected $table = 'messages';
}

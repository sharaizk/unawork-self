<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PodUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pod_id',
        'permission',
        'status',
        'reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pod()
    {
        return $this->belongsTo(Pod::class);
    }
}

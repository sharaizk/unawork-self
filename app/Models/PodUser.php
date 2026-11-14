<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\IsUniqueMembership;
use Illuminate\Database\Eloquent\Model;

class PodUser extends Model
{
    use IsUniqueMembership;

    public static function membershipParentKey(): string
    {
        return 'pod_id';
    }

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

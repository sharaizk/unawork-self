<?php

namespace App\Models;

use App\Models\Concerns\IsUniqueMembership;
use Illuminate\Database\Eloquent\Model;

class UserProject extends Model
{
    use IsUniqueMembership;

    public static function membershipParentKey(): string
    {
        return 'project_id';
    }

    protected $fillable = [
        'user_id','project_id','is_active','permission','status'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
    
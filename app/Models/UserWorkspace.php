<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class UserWorkspace extends Model
{
    use HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'user_id','workspace_id','permission','is_active','status','is_enable_login'
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

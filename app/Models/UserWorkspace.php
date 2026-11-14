<?php

namespace App\Models;

use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Models\Concerns\IsUniqueMembership;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class UserWorkspace extends Model
{
    use HasRoles;
    use IsUniqueMembership;

    public static function membershipParentKey(): string
    {
        return 'workspace_id';
    }

    protected $guard_name = WorkspacePermission::GUARD;

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

    /**
     * Grant the workspace owner every workspace permission (and only those): the
     * WorkspacePermission enum values under this model's guard. Unrelated Spatie permissions
     * or other guards are never granted. Requires PermissionsSeeder to have run.
     */
    public function assignAllPermissionsToCreator()
    {
        $this->givePermissionTo(WorkspacePermission::values());
    }
}

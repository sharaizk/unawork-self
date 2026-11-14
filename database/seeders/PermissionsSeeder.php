<?php

namespace Database\Seeders;

use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the workspace permissions: every App\Enums\UserWorkspace\Permission case under
 * WorkspacePermission::GUARD.
 *
 * Idempotent: looks each permission up by (name, guard_name) directly in the database (not
 * Spatie's cache, which may be stale) and creates only the missing ones. Existing permissions
 * keep their IDs and assignments; other permissions and guards are never touched.
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (WorkspacePermission::cases() as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission->value, 'guard_name' => WorkspacePermission::GUARD]);
        }

        $registrar->forgetCachedPermissions();
    }
}

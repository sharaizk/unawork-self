<?php

namespace Database\Seeders;

use App\Enums\User\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the account persona roles: every App\Enums\User\UserRole case under UserRole::GUARD.
 * (Workspace permissions are seeded by PermissionsSeeder and belong to UserWorkspace records,
 * never to these roles.)
 *
 * Idempotent: looks each role up by (name, guard_name) in the database and creates only the
 * missing ones. Existing roles keep their IDs and assignments; other roles and guards are
 * never touched.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::query()->firstOrCreate(['name' => $role->value, 'guard_name' => UserRole::GUARD]);
        }

        $registrar->forgetCachedPermissions();
    }
}

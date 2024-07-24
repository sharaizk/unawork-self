<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'users_index', 'guard_name' => 'web']);
        Permission::create(['name' => 'users_edit', 'guard_name' => 'web']);

        Permission::create(['name' => 'news_index', 'guard_name' => 'web']);
        Permission::create(['name' => 'news_edit', 'guard_name' => 'web']);

        Permission::create(['name' => 'projects_index', 'guard_name' => 'web']);
        Permission::create(['name' => 'projects_edit', 'guard_name' => 'web']);
    }
}

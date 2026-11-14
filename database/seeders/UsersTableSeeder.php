<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Enums\User\UserType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Utility;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('123123123'),
            'type' => UserType::ADMIN->value, // administrators hold no company/employee role
            'email_verified_at' => date('Y-m-d i:h:s'),
            'lang' => 'en',
        ]);

        User::defaultEmail();
        User::seed_languages();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;


class DatabaseSeeder extends Seeder
{
    /**
     * Seeded first, on every path (CLI, installer, updater): the account roles and workspace
     * permissions that registration and workspace ownership rely on. Both are idempotent.
     */
    public const FOUNDATION_SEEDERS = [
        RolesAndPermissionsSeeder::class, // account roles (App\Enums\User\UserRole)
        PermissionsSeeder::class,         // workspace permissions (App\Enums\UserWorkspace\Permission)
    ];

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(self::FOUNDATION_SEEDERS);

        Artisan::call('module:migrate LandingPage');  
        Artisan::call('module:seed LandingPage');
        $this->call(NotificationSeeder::class);
        // Reference catalog (industries, skills, professional roles); idempotent, never destructive.
        $this->call(CatalogSeeder::class);

        if((\Request::route() != null) && (\Request::route()->getName()!='LaravelUpdater::database'))
        {
            $this->call(PlansTableSeeder::class);
            $this->call(UsersTableSeeder::class);
            $this->call(AiTemplateSeeder::class);
            
        }else{
            
            User::seed_languages();
        }
    }
}

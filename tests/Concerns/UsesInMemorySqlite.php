<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forces an in-memory SQLite database before the application boots, so a test can never
 * touch the database configured in .env (phpunit.xml does not isolate it). The real
 * migrations are MySQL-specific, so tests build only the tables they need.
 *
 * Same safety pattern as tests/Feature/Authorization/PodAndProjectClaimAuthorizationTest.php.
 */
trait UsesInMemorySqlite
{
    private static array $inMemorySqliteOriginalEnv = [];

    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DATABASE_URL' => ''] as $key => $value) {
            if (!array_key_exists($key, self::$inMemorySqliteOriginalEnv)) {
                self::$inMemorySqliteOriginalEnv[$key] = $_SERVER[$key] ?? null;
            }
            $_SERVER[$key] = $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.url', null);

        return $app;
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$inMemorySqliteOriginalEnv as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key], $_ENV[$key]);
                putenv($key);
            } else {
                $_SERVER[$key] = $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
        self::$inMemorySqliteOriginalEnv = [];

        parent::tearDownAfterClass();
    }

    protected function assertUsingInMemorySqlite(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: these tests must use an in-memory SQLite database.');
        }
    }

    /** Tables used by account identity, registration and invitation flows. */
    protected function createIdentitySchema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password')->nullable();
            $t->rememberToken();
            $t->string('type', 20)->default('user');
            $t->integer('plan')->nullable();
            $t->date('plan_expire_date')->nullable();
            $t->string('lang')->nullable();
            $t->string('avatar')->nullable();
            $t->unsignedBigInteger('currant_workspace')->nullable();
            $t->integer('is_enable_login')->default(1);
            $t->integer('is_trial_done')->default(0);
            $t->string('referral_code')->nullable();
            $t->string('referral_used')->nullable();
            $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('currant_workspace')->nullable();
            $t->timestamps();
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->string('lang')->nullable();
            $t->string('currency_code')->nullable();
            $t->string('paypal_mode')->nullable();
            $t->string('theme_color')->nullable();
            $t->string('color_flag')->nullable();
            $t->string('ownerable_type')->nullable();
            $t->unsignedBigInteger('ownerable_id')->nullable();
            $t->integer('is_active')->default(1);
            $t->timestamps();
        });
        // Workspace::create() seeds default task and bug stages.
        foreach (['stages', 'bug_stages'] as $stageTable) {
            Schema::create($stageTable, function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('color')->nullable();
                $t->unsignedBigInteger('workspace_id');
                $t->boolean('complete')->default(false);
                $t->integer('order')->default(0);
                $t->timestamps();
            });
        }
        Schema::create('user_workspaces', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('workspace_id');
            $t->string('permission');
            $t->integer('is_active')->default(1);
            $t->string('status')->default('active');
            $t->integer('is_enable_login')->default(1);
            $t->timestamps();
            $t->unique(['user_id', 'workspace_id'], 'user_workspaces_user_id_workspace_id_unique'); // UNA-004, as in production
        });
        Schema::create('client_workspaces', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('workspace_id');
            $t->integer('is_active')->default(1);
            $t->timestamps();
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('workspace');
            $t->unsignedBigInteger('created_by');
            $t->integer('is_active')->default(1);
            $t->timestamps();
        });
        Schema::create('user_projects', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('project_id');
            $t->integer('is_active')->default(1);
            $t->string('status')->default('active');
            $t->text('permission')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'project_id'], 'user_projects_user_id_project_id_unique'); // UNA-004, as in production
        });
        Schema::create('client_projects', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('project_id');
            $t->integer('is_active')->default(1);
            $t->timestamps();
        });
        Schema::create('pods', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('image')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->boolean('inner_enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('pod_users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('pod_id');
            $t->string('permission');
            $t->string('state', 40); // UNA-006
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'pod_id'], 'pod_users_user_id_pod_id_unique'); // UNA-004, as in production
            $t->index(['pod_id', 'state'], 'pod_users_pod_id_state_index');
            $t->index(['user_id', 'state'], 'pod_users_user_id_state_index');
        });
        Schema::create('pod_user_state_histories', function (Blueprint $t) { // UNA-006
            $t->id();
            $t->unsignedBigInteger('pod_user_id');
            $t->string('from_state', 40)->nullable();
            $t->string('to_state', 40);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->text('reason')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->foreign('pod_user_id')->references('id')->on('pod_users')->cascadeOnDelete();
            $t->index(['pod_user_id', 'id']);
        });
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->integer('max_users')->default(-1);
            $t->integer('max_workspaces')->default(-1);
            $t->integer('max_projects')->default(-1);
            $t->integer('max_clients')->default(-1);
            $t->string('duration')->default('unlimited');
            $t->timestamps();
        });
        Schema::create('job_vacancies', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('title')->nullable();
            $t->timestamps();
        });
        Schema::create('email_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('from')->nullable();
            $t->timestamps();
        });
        Schema::create('email_template_langs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('parent_id');
            $t->string('lang')->nullable();
            $t->string('subject')->nullable();
            $t->text('content')->nullable();
            $t->string('from')->nullable();
            $t->timestamps();
        });
        Schema::create('languages', function (Blueprint $t) {
            $t->id();
            $t->string('lang_code');
            $t->string('lang_fullname')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('value');
            $t->integer('created_by');
            $t->timestamps();
        });
        Schema::create('user_email_templates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('template_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('workspace_id')->nullable();
            $t->integer('is_active')->default(1);
            $t->timestamps();
        });
        Schema::create('admin_payment_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('value');
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id')->nullable();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('project_id')->nullable();
            $t->unsignedBigInteger('related_id')->nullable();
            $t->string('type');
            $t->text('data');
            $t->integer('is_read')->default(0);
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        $this->createPermissionSchema();
        $this->createProfileSchema();
        $this->createCompanySchema();
    }

    /**
     * Company profile columns on workspaces (existing production columns plus UNA-010) and the
     * worker-requirement tables, built by the real UNA-010 migrations.
     */
    protected function createCompanySchema(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            foreach (['company', 'address', 'city', 'state', 'zipcode', 'country', 'telephone', 'logo', 'website', 'linkedin', 'facebook'] as $column) {
                $t->string($column)->nullable();
            }
            $t->text('description')->nullable();
            $t->unsignedBigInteger('industry_id')->nullable();
        });
        (require database_path('migrations/2026_11_18_000200_add_business_onboarding_to_workspaces.php'))->up();
        (require database_path('migrations/2026_11_18_000300_create_workspace_worker_requirements_table.php'))->up();
        Schema::create('pods_skills', function (Blueprint $t) { // POD-level skill tags
            $t->id();
            $t->unsignedBigInteger('pod_id');
            $t->unsignedBigInteger('skill_id');
            $t->timestamps();
            $t->unique(['pod_id', 'skill_id']);
        });
        // UNA-011: POD draft/publish lifecycle and POD-owned requirements.
        (require database_path('migrations/2026_11_18_000400_add_publication_lifecycle_to_pods.php'))->up();
        // UNA-013: matching targets on both requirement tables, and the persisted match snapshots.
        (require database_path('migrations/2026_11_19_000000_add_matching_configuration_to_worker_requirements.php'))->up();
        (require database_path('migrations/2026_11_19_000100_create_pod_matches_tables.php'))->up();
        // UNA-014: POD minimum match threshold and the company default.
        (require database_path('migrations/2026_11_19_000200_add_matching_thresholds.php'))->up();
        // UNA-016: pod_matches.algorithm.
        (require database_path('migrations/2026_11_19_000300_add_algorithm_to_pod_matches.php'))->up();
        // UNA-017: recommendation dismissals.
        (require database_path('migrations/2026_11_19_000400_create_pod_recommendation_dismissals_table.php'))->up();
    }

    /**
     * Give a company account a 100% business profile on its own workspace (UNA-010). Use it for
     * companies in tests unrelated to business onboarding (e.g. POD owners).
     */
    protected function completeBusinessProfile(\App\Models\User $company): \App\Models\Workspace
    {
        $workspace = $company->ownWorkspace();
        if (!$workspace) {
            $workspace = \App\Models\Workspace::create(['name' => $company->name . ' WS', 'created_by' => $company->id, 'lang' => 'en']);
            $workspace->ownerable()->associate($company)->save();
        }
        $industry = \App\Models\Industry::firstOrCreate(['name' => 'Technology']);
        $workspace->forceFill([
            'company' => 'Acme Ltd',
            'logo' => $workspace->id . '_company-logo-fixture.png',
            'industry_id' => $industry->id,
            'contact_name' => 'Casey Contact',
            'contact_email' => 'contact@acme.example.test',
            'telephone' => '+94 11 000 0000',
            'city' => 'Colombo',
            'country' => 'Sri Lanka',
            'website' => 'https://acme.example.test',
            'linkedin' => 'https://www.linkedin.com/company/acme',
            'description' => str_repeat('Acme builds software with distributed teams. ', 4),
            'matching_mode' => 'assisted',
            'default_pod_application_approval' => 'manual',
            'default_inner_circle_approval' => 'manual',
        ])->save();

        $role = \App\Models\JobTitle::firstOrCreate(['title' => 'Backend Developer']);
        $skill = \App\Models\Skill::firstOrCreate(['name' => 'PHP']);
        $requirement = new \App\Models\WorkspaceWorkerRequirement([
            'job_title_id' => $role->id, 'headcount' => 2, 'preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract', 'is_active' => true,
        ]);
        $requirement->forceFill(['workspace_id' => $workspace->id])->save();
        $requirement->skills()->sync([$skill->id]);

        return $workspace->fresh();
    }

    /**
     * Employee profile tables in their current shape: profiles (incl. industry_id), user_skills (with
     * proficiency and the unique pair), skills, job_titles, industries with the skill/role industry
     * tags, and the structured profile_* tables.
     */
    protected function createProfileSchema(): void
    {
        Schema::create('skills', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('job_titles', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });
        Schema::create('user_skills', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('skill_id');
            $t->timestamps();
            $t->string('proficiency', 20)->nullable(); // UNA-007
            $t->unique(['user_id', 'skill_id'], 'user_skills_user_id_skill_id_unique');
        });
        Schema::create('profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->unsignedBigInteger('user_id');
            $t->string('country')->nullable();
            $t->string('state')->nullable();
            $t->string('city')->nullable();
            $t->string('zip_code')->nullable();
            $t->string('address')->nullable();
            $t->string('phone_number')->nullable();
            $t->string('cv_file')->nullable();
            $t->timestamps();
            $t->string('website')->nullable();
            $t->text('about')->nullable();
            $t->decimal('years_experience', 4, 1)->nullable();
            $t->string('linkedin_url', 500)->nullable();
            $t->string('github_url', 500)->nullable();
            $t->string('timezone', 64)->nullable();
            $t->string('availability_status', 32)->nullable();
            $t->date('available_from')->nullable();
            $t->unsignedSmallInteger('hours_per_week')->nullable();
            $t->string('preferred_work_mode', 32)->nullable();
            $t->string('preferred_engagement_type', 32)->nullable();
            $t->boolean('open_to_relocation')->nullable();
            $t->boolean('willing_to_travel')->nullable();
        });
        (require database_path('migrations/2026_11_17_000200_create_profile_detail_tables.php'))->up();
        Schema::create('industries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        // Skill/role industry tags and profiles.industry_id.
        (require database_path('migrations/2026_11_18_000100_add_industry_relations_to_catalogs.php'))->up();
    }

    /**
     * Give $user a profile that satisfies every ProfileCompletionService section (100%). Use it for
     * employees in tests unrelated to profile eligibility (UNA-007).
     */
    protected function completeEmployeeProfile(\App\Models\User $user): \App\Models\Profile
    {
        $jobTitle = \App\Models\JobTitle::firstOrCreate(['title' => 'Engineer']);
        $profile = \App\Models\Profile::where('user_id', $user->id)->first() ?? new \App\Models\Profile();
        $profile->forceFill([
            'user_id' => $user->id,
            'about' => 'Fixture summary',
            'job_title_id' => $jobTitle->id,
            'years_experience' => 5,
            'country' => 'Sri Lanka',
            'city' => 'Colombo',
            'timezone' => 'Asia/Colombo',
            'availability_status' => 'immediately',
            'hours_per_week' => 40,
            'preferred_work_mode' => 'remote',
            'preferred_engagement_type' => 'contract',
            'linkedin_url' => 'https://www.linkedin.com/in/fixture',
        ])->save();

        $skills = [];
        foreach (['PHP', 'Laravel', 'SQL'] as $name) {
            $skills[\App\Models\Skill::firstOrCreate(['name' => $name])->id] = ['proficiency' => 'advanced'];
        }
        $user->skills()->syncWithoutDetaching($skills);

        $profile->experiences()->forceCreate(['profile_id' => $profile->id, 'employer' => 'Acme', 'job_title' => 'Engineer', 'is_current' => true]);
        $profile->educations()->forceCreate(['profile_id' => $profile->id, 'institution' => 'University']);
        $profile->preferredRoles()->forceCreate(['profile_id' => $profile->id, 'job_title_id' => $jobTitle->id]);
        $profile->projectTypes()->forceCreate(['profile_id' => $profile->id, 'project_type' => 'long_term']);

        $user->unsetRelation('profile')->unsetRelation('skills');

        return $profile;
    }

    /** Tables layouts.admin (topnav, sidebar, messenger) reads on every page. */
    protected function createAdminLayoutSchema(): void
    {
        \Illuminate\Support\Facades\Schema::create('threads', function ($t) {
            $t->id();
            $t->string('subject')->nullable();
            $t->string('slug')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        \Illuminate\Support\Facades\Schema::create('participants', function ($t) {
            $t->id();
            $t->unsignedBigInteger('thread_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamp('last_read')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        \Illuminate\Support\Facades\Schema::create('messages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('thread_id');
            $t->unsignedBigInteger('user_id');
            $t->text('body')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        foreach (['tasks', 'bug_reports'] as $table) {
            \Illuminate\Support\Facades\Schema::create($table, function ($t) {
                $t->id();
                $t->string('title')->nullable();
                $t->unsignedBigInteger('project_id')->nullable();
                $t->string('assign_to')->nullable();
                $t->timestamps();
            });
        }
    }

    /** Run seeders in-process (no Artisan), e.g. $this->runSeeders([RolesAndPermissionsSeeder::class]). */
    protected function runSeeders(array $seeders): void
    {
        foreach ($seeders as $seeder) {
            $this->app->make($seeder)->setContainer($this->app)->__invoke();
        }
    }

    protected function createPermissionSchema(): void
    {
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']); // as in Spatie's create_permission_tables migration
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']); // as in Spatie's create_permission_tables migration
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });
    }
}

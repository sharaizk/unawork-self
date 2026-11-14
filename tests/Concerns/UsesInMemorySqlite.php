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
            $t->string('status');
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'pod_id'], 'pod_users_user_id_pod_id_unique'); // UNA-004, as in production
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
        Schema::create('user_skills', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('skill_id');
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

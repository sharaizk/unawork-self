<?php

namespace Tests\Feature\Company;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/** UNA-010 migrations on the pre-UNA-010 schema: existing workspaces and PODs are untouched. In-memory SQLite only. */
class BusinessOnboardingMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATIONS = [
        'migrations/2026_11_18_000200_add_business_onboarding_to_workspaces.php',
        'migrations/2026_11_18_000300_create_workspace_worker_requirements_table.php',
    ];

    public function test_existing_workspaces_and_pods_are_not_changed_and_rollback_is_clean(): void
    {
        $this->assertUsingInMemorySqlite();
        foreach (['job_titles' => 'title', 'skills' => 'name'] as $table => $column) {
            Schema::create($table, function (Blueprint $t) use ($column) {
                $t->id();
                $t->string($column);
                $t->timestamps();
            });
        }
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('created_by');
            $t->string('company')->nullable();
            $t->string('telephone')->nullable();
            $t->text('description')->nullable();
            $t->string('website')->nullable();
            $t->string('ownerable_type')->nullable();
            $t->unsignedBigInteger('ownerable_id')->nullable();
            $t->timestamps();
        });
        Schema::create('pods', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
        });
        DB::table('workspaces')->insert([
            ['id' => 1, 'name' => 'Acme', 'created_by' => 1, 'company' => 'Acme', 'telephone' => '1', 'description' => 'Legacy', 'website' => 'https://acme.test', 'ownerable_type' => 'App\\Models\\User', 'ownerable_id' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 2, 'name' => 'Pod-workspace', 'created_by' => 1, 'company' => null, 'telephone' => null, 'description' => null, 'website' => null, 'ownerable_type' => 'App\\Models\\Pod', 'ownerable_id' => 1, 'created_at' => '2025-01-02 00:00:00', 'updated_at' => '2025-01-02 00:00:00'],
        ]);
        DB::table('pods')->insert(['id' => 1, 'title' => 'Live POD', 'created_by' => 1, 'created_at' => '2025-01-02 00:00:00', 'updated_at' => '2025-01-02 00:00:00']);
        $before = [DB::table('workspaces')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(), DB::table('pods')->get()->map(fn ($r) => (array) $r)->all()];

        foreach (self::MIGRATIONS as $migration) {
            (require database_path($migration))->up();
        }

        $after = DB::table('workspaces')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        foreach ($before[0] as $i => $row) {
            foreach ($row as $column => $value) {
                $this->assertSame($value, $after[$i][$column], "workspaces.$column");
            }
            foreach (['contact_name', 'contact_email', 'contact_position', 'matching_mode', 'default_pod_application_approval', 'default_inner_circle_approval', 'business_onboarding_started_at', 'business_onboarding_completed_at', 'business_onboarding_step'] as $new) {
                $this->assertNull($after[$i][$new], $new);
            }
        }
        $this->assertSame($before[1], DB::table('pods')->get()->map(fn ($r) => (array) $r)->all());
        $this->assertTrue(Schema::hasIndex('workspaces', 'workspaces_matching_mode_index'));
        $this->assertTrue(Schema::hasIndex('workspace_worker_requirement_skill', 'wwrs_requirement_skill_unique', 'unique'));

        foreach (array_reverse(self::MIGRATIONS) as $migration) {
            (require database_path($migration))->down();
        }
        $this->assertFalse(Schema::hasTable('workspace_worker_requirements'));
        $this->assertFalse(Schema::hasColumn('workspaces', 'matching_mode'));
        $this->assertSame('Acme', DB::table('workspaces')->where('id', 1)->value('company'));
    }
}

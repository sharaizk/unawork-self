<?php

namespace Tests\Feature\Profile;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-007: the three profile migrations run against the pre-UNA-007 schema (legacy NOT NULL
 * profile columns, user_skills without proficiency/uniqueness, existing POD memberships).
 * In-memory SQLite only.
 */
class ProfileMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATIONS = [
        'migrations/2026_11_17_000000_extend_profiles_for_structured_onboarding.php',
        'migrations/2026_11_17_000100_add_proficiency_to_user_skills.php',
        'migrations/2026_11_17_000200_create_profile_detail_tables.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createLegacySchema();
        $this->seedLegacy();
    }

    public function test_legacy_profiles_are_preserved_and_readable(): void
    {
        $before = DB::table('profiles')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $this->migrateUp();

        $after = DB::table('profiles')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        foreach ($before as $i => $row) {
            foreach ($row as $column => $value) {
                $this->assertSame($value, $after[$i][$column], "profiles.$column changed");
            }
            foreach (['years_experience', 'linkedin_url', 'github_url', 'timezone', 'availability_status', 'available_from',
                'hours_per_week', 'preferred_work_mode', 'preferred_engagement_type', 'open_to_relocation', 'willing_to_travel'] as $new) {
                $this->assertNull($after[$i][$new], $new);
            }
        }

        $profile = Profile::where('user_id', 1)->sole();
        $this->assertSame(['Sri Lanka', 'Colombo', '00100', 'profile-files/cv.pdf', 'Legacy about', 'https://legacy.example.test', 1],
            [$profile->country, $profile->city, $profile->zip_code, $profile->cv_file, $profile->about, $profile->website, $profile->job_title_id]);
        $this->assertNull($profile->availability_status);
        $this->assertSame('Developer', $profile->jobTitle->title);
        $this->assertTrue($profile->experiences->isEmpty());

        // Drafts: the legacy required columns now accept nulls.
        DB::table('profiles')->insert(['user_id' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertNull(DB::table('profiles')->where('user_id', 2)->value('country'));

        foreach (['profiles_years_experience_index', 'profiles_timezone_index', 'profiles_availability_status_index',
            'profiles_preferred_work_mode_index', 'profiles_preferred_engagement_type_index', 'profiles_country_city_index'] as $index) {
            $this->assertTrue(Schema::hasIndex('profiles', $index), $index);
        }
    }

    public function test_user_skills_gain_proficiency_and_duplicate_pairs_are_collapsed(): void
    {
        $this->migrateUp();

        $rows = DB::table('user_skills')->orderBy('id')->get(['id', 'user_id', 'skill_id', 'proficiency']);
        $this->assertSame([[1, 1, 1, null], [2, 1, 2, null], [4, 2, 1, null]], $rows->map(fn ($r) => [(int) $r->id, (int) $r->user_id, (int) $r->skill_id, $r->proficiency])->all());
        $this->assertTrue(Schema::hasIndex('user_skills', 'user_skills_user_id_skill_id_unique', 'unique'));

        try {
            DB::table('user_skills')->insert(['user_id' => 1, 'skill_id' => 1]);
            $this->fail('duplicate pair accepted');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }

        // Legacy rows remain valid and readable through the relation.
        $this->assertSame([null, null], User::findOrFail(1)->skills->pluck('pivot.proficiency')->all());
    }

    public function test_existing_pod_memberships_and_access_are_not_rewritten(): void
    {
        $tables = ['pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->map(fn ($r) => (array) $r)->all()])->all();
        $before = $snapshot();

        $this->migrateUp();

        $this->assertEquals($before, $snapshot());
        $this->assertSame('outer_circle', DB::table('pod_users')->where('user_id', 1)->value('state'), 'incomplete legacy member stays active');
    }

    public function test_detail_tables_are_created_with_constraints_and_dropped_on_rollback(): void
    {
        $this->migrateUp();

        foreach (['profile_experiences', 'profile_educations', 'profile_certifications', 'profile_portfolios', 'profile_preferred_roles', 'profile_project_types'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        DB::table('profile_preferred_roles')->insert(['profile_id' => 1, 'job_title_id' => 1]);
        DB::table('profile_project_types')->insert(['profile_id' => 1, 'project_type' => 'hourly']);
        foreach ([['profile_preferred_roles', ['profile_id' => 1, 'job_title_id' => 1]], ['profile_project_types', ['profile_id' => 1, 'project_type' => 'hourly']]] as [$table, $row]) {
            try {
                DB::table($table)->insert($row);
                $this->fail("duplicate $table accepted");
            } catch (UniqueConstraintViolationException) {
                $this->addToAssertionCount(1);
            }
        }

        // Records are deleted with their profile.
        DB::table('profile_experiences')->insert(['profile_id' => 1, 'employer' => 'Acme', 'job_title' => 'Dev', 'is_current' => 1]);
        DB::table('profiles')->where('id', 1)->delete();
        $this->assertSame(0, DB::table('profile_experiences')->count());
        $this->assertSame(0, DB::table('profile_preferred_roles')->count());

        foreach (array_reverse(self::MIGRATIONS) as $migration) {
            (require database_path($migration))->down();
        }
        $this->assertFalse(Schema::hasTable('profile_experiences'));
        $this->assertFalse(Schema::hasColumn('profiles', 'timezone'));
        $this->assertFalse(Schema::hasColumn('user_skills', 'proficiency'));
        $this->assertTrue(Schema::hasColumn('profiles', 'website'));
    }

    // ================================================================ helpers

    private function migrateUp(): void
    {
        foreach (self::MIGRATIONS as $migration) {
            (require database_path($migration))->up();
        }
    }

    /** profiles / user_skills / POD tables as they exist before UNA-007. */
    private function createLegacySchema(): void
    {
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('type', 20)->default('user');
            $t->timestamps();
        });
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
        Schema::create('user_skills', function (Blueprint $t) { // 2024_06_06_162502
            $t->id();
            $t->foreignId('user_id')->constrained()->onDelete('cascade');
            $t->foreignId('skill_id')->constrained()->onDelete('cascade');
            $t->timestamps();
        });
        Schema::create('profiles', function (Blueprint $t) { // 2024_06_12 + 2024_06_25 + 2024_09_10
            $t->id();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->foreign('job_title_id')->references('id')->on('job_titles')->onDelete('set null');
            $t->foreignId('user_id')->constrained()->onDelete('cascade');
            $t->string('country');
            $t->string('state')->nullable();
            $t->string('city');
            $t->string('zip_code');
            $t->string('address');
            $t->string('phone_number');
            $t->string('cv_file');
            $t->timestamps();
            $t->string('website')->nullable();
            $t->text('about')->nullable();
        });
        Schema::create('pod_users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('pod_id');
            $t->string('permission');
            $t->string('state', 40);
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'pod_id']);
        });
        Schema::create('pod_user_state_histories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pod_user_id');
            $t->string('from_state', 40)->nullable();
            $t->string('to_state', 40);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->text('reason')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('user_workspaces', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('workspace_id');
            $t->string('permission');
            $t->string('status')->default('active');
            $t->integer('is_active')->default(1);
            $t->timestamps();
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
    }

    private function seedLegacy(): void
    {
        $now = '2025-01-01 10:00:00';
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Legacy', 'email' => 'legacy@example.test'],
            ['id' => 2, 'name' => 'Other', 'email' => 'other@example.test'],
        ]);
        DB::table('skills')->insert([['id' => 1, 'name' => 'PHP'], ['id' => 2, 'name' => 'SQL']]);
        DB::table('job_titles')->insert(['id' => 1, 'title' => 'Developer']);
        DB::table('user_skills')->insert([
            ['id' => 1, 'user_id' => 1, 'skill_id' => 1, 'created_at' => $now],
            ['id' => 2, 'user_id' => 1, 'skill_id' => 2, 'created_at' => $now],
            ['id' => 3, 'user_id' => 1, 'skill_id' => 1, 'created_at' => '2025-02-01 10:00:00'], // duplicate pair
            ['id' => 4, 'user_id' => 2, 'skill_id' => 1, 'created_at' => $now],
        ]);
        DB::table('profiles')->insert([
            'id' => 1, 'job_title_id' => 1, 'user_id' => 1, 'country' => 'Sri Lanka', 'state' => null, 'city' => 'Colombo',
            'zip_code' => '00100', 'address' => '1 Main Street', 'phone_number' => '+94 11 000 0000', 'cv_file' => 'profile-files/cv.pdf',
            'created_at' => $now, 'updated_at' => $now, 'website' => 'https://legacy.example.test', 'about' => 'Legacy about',
        ]);
        DB::table('pod_users')->insert(['id' => 1, 'user_id' => 1, 'pod_id' => 1, 'permission' => 'Member', 'state' => 'outer_circle', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('pod_user_state_histories')->insert(['pod_user_id' => 1, 'to_state' => 'outer_circle', 'created_at' => $now]);
        DB::table('user_workspaces')->insert(['user_id' => 1, 'workspace_id' => 5, 'permission' => 'Member', 'status' => 'active', 'is_active' => 1]);
        DB::table('model_has_permissions')->insert(['permission_id' => 1, 'model_type' => 'App\\Models\\UserWorkspace', 'model_id' => 1]);
    }
}

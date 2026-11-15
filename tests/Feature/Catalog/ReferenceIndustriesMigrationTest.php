<?php

namespace Tests\Feature\Catalog;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * The idempotent reference-industry data migration (deploys run `migrate`, never `db:seed`).
 * In-memory SQLite only.
 */
class ReferenceIndustriesMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATION = 'migrations/2026_11_18_000000_install_reference_industries.php';
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        Schema::create('industries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('industry_id')->nullable();
        });
        $this->migration = require database_path(self::MIGRATION);
    }

    public function test_installs_all_54_canonical_industries_with_timestamps(): void
    {
        $this->migration->up();

        $names = DB::table('industries')->orderBy('id')->pluck('name')->all();
        $this->assertCount(54, $names);
        $this->assertSame($this->migration::INDUSTRIES, $names);
        $this->assertCount(54, array_unique(array_map('mb_strtolower', $names)));
        foreach (['Healthcare', 'Gaming & Interactive Entertainment', 'Hospitality & Accommodations', 'Childcare & Early Childhood Services'] as $name) {
            $this->assertContains($name, $names);
        }
        foreach (['HealthCare', 'Gamin & Interactive Entertainment', 'Hospitality & Accomodations'] as $misspelled) {
            $this->assertNotContains($misspelled, $names);
        }
        $this->assertSame(0, DB::table('industries')->whereNull('created_at')->orWhereNull('updated_at')->count());
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        $this->migration->up();
        $first = DB::table('industries')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $this->migration->up();
        (require database_path(self::MIGRATION))->up(); // a retried deploy loads the file again

        $this->assertSame($first, DB::table('industries')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }

    public function test_existing_and_custom_industries_keep_their_ids_names_and_workspaces(): void
    {
        DB::table('industries')->insert([
            ['id' => 7, 'name' => 'HealthCare', 'created_at' => '2024-09-10 10:00:00', 'updated_at' => '2024-09-10 10:00:00'],
            ['id' => 9, 'name' => '  banking ', 'created_at' => '2024-09-10 10:00:00', 'updated_at' => '2024-09-10 10:00:00'],
            ['id' => 12, 'name' => 'Space Mining', 'created_at' => '2024-09-10 10:00:00', 'updated_at' => '2024-09-10 10:00:00'],
        ]);
        DB::table('workspaces')->insert([['name' => 'Clinic', 'industry_id' => 7], ['name' => 'Rockets', 'industry_id' => 12]]);
        $before = DB::table('industries')->whereIn('id', [7, 9, 12])->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $this->migration->up();

        $this->assertSame($before, DB::table('industries')->whereIn('id', [7, 9, 12])->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
        $this->assertSame(3 + 52, DB::table('industries')->count(), 'Healthcare and Banking already present (case/whitespace-insensitive)');
        $this->assertSame(0, DB::table('industries')->where('name', 'Healthcare')->count());
        $this->assertSame([7, 12], DB::table('workspaces')->orderBy('id')->pluck('industry_id')->map(fn ($v) => (int) $v)->all());
        $this->assertGreaterThan(12, (int) DB::table('industries')->where('name', 'Construction')->value('id'));
    }

    public function test_rollback_removes_only_unused_industries_it_installed(): void
    {
        DB::table('industries')->insert(['id' => 1, 'name' => 'Technology', 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00']);
        $this->migration->up();
        $banking = (int) DB::table('industries')->where('name', 'Banking')->value('id');
        $energy = (int) DB::table('industries')->where('name', 'Energy')->value('id');
        DB::table('workspaces')->insert(['name' => 'Bank', 'industry_id' => $banking]);
        DB::table('industries')->where('id', $energy)->update(['name' => 'Energy & Utilities', 'updated_at' => now()]);

        $this->migration->down();

        $this->assertEqualsCanonicalizing(['Technology', 'Banking', 'Energy & Utilities'], DB::table('industries')->pluck('name')->all());
    }
}

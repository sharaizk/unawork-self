<?php

namespace Tests\Feature\Matching;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/** UNA-013 migrations on the pre-UNA-013 schema: additive, defaults, indexes, reversible. In-memory SQLite only. */
class PodMatchingMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const CONFIGURATION = 'migrations/2026_11_19_000000_add_matching_configuration_to_worker_requirements.php';
    private const MATCHES = 'migrations/2026_11_19_000100_create_pod_matches_tables.php';
    private const NEW_COLUMNS = ['required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type', 'mandatory_criteria'];

    public function test_columns_tables_indexes_defaults_and_down(): void
    {
        $this->assertUsingInMemorySqlite();
        foreach (['users' => 'name', 'pods' => 'title'] as $table => $column) {
            Schema::create($table, function (Blueprint $t) use ($column) {
                $t->id();
                $t->string($column);
                $t->timestamps();
            });
        }
        foreach (['workspace_worker_requirements', 'pod_worker_requirements'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('pod_id')->nullable();
                $t->string('location')->nullable();
                $t->timestamps();
            });
        }
        DB::table('workspace_worker_requirements')->insert(['location' => 'Colombo']);
        DB::table('pod_worker_requirements')->insert(['location' => 'Kandy']);

        (require database_path(self::CONFIGURATION))->up();
        (require database_path(self::MATCHES))->up();

        foreach (['workspace_worker_requirements', 'pod_worker_requirements'] as $table) {
            $this->assertTrue(Schema::hasColumns($table, self::NEW_COLUMNS), $table);
            $row = (array) DB::table($table)->first();
            $this->assertSame(array_fill_keys(self::NEW_COLUMNS, null), array_intersect_key($row, array_flip(self::NEW_COLUMNS)), 'existing rows: nothing configured, nothing mandatory');
        }
        $this->assertTrue(Schema::hasColumns('pod_matches', ['pod_id', 'pod_worker_requirement_id', 'user_id', 'score', 'status', 'active_weight', 'scoring_version', 'evaluated_at', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('pod_match_reasons', ['pod_match_id', 'criterion', 'position', 'active', 'possible_points', 'awarded_points', 'passed', 'mandatory', 'disqualifying', 'code', 'context']));
        foreach ([
            'pod_matches' => ['pod_matches_user_requirement_unique', 'pod_matches_requirement_status_score_index', 'pod_matches_pod_status_score_index'],
            'pod_match_reasons' => ['pod_match_reasons_match_criterion_unique', 'pod_match_reasons_match_position_index', 'pod_match_reasons_criterion_passed_index'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertTrue(Schema::hasIndex($table, $index), $index);
            }
        }

        // Defaults and the unique pair.
        DB::table('users')->insert(['id' => 1, 'name' => 'E']);
        DB::table('pods')->insert(['id' => 1, 'title' => 'P']);
        $match = ['pod_id' => 1, 'pod_worker_requirement_id' => 1, 'user_id' => 1, 'status' => 'eligible', 'scoring_version' => 'v', 'evaluated_at' => '2026-06-15 12:00:00'];
        DB::table('pod_matches')->insert($match);
        $row = DB::table('pod_matches')->first();
        $this->assertSame([0.0, 0], [(float) $row->score, (int) $row->active_weight]);
        DB::table('pod_match_reasons')->insert(['pod_match_id' => $row->id, 'criterion' => 'role', 'position' => 3, 'active' => true, 'code' => 'role_primary']);
        $reason = DB::table('pod_match_reasons')->first();
        $this->assertSame([0, 0.0, 0, 0, 0, null], [(int) $reason->possible_points, (float) $reason->awarded_points, (int) $reason->passed, (int) $reason->mandatory, (int) $reason->disqualifying, $reason->context]);
        try {
            DB::table('pod_matches')->insert($match);
            $this->fail('duplicate (user, requirement) match');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        (require database_path(self::MATCHES))->down();
        (require database_path(self::CONFIGURATION))->down();

        $this->assertFalse(Schema::hasTable('pod_matches'));
        $this->assertFalse(Schema::hasTable('pod_match_reasons'));
        foreach (['workspace_worker_requirements', 'pod_worker_requirements'] as $table) {
            foreach (self::NEW_COLUMNS as $column) {
                $this->assertFalse(Schema::hasColumn($table, $column), "{$table}.{$column}");
            }
            $this->assertSame(1, DB::table($table)->count(), 'existing rows kept');
        }
        $this->assertSame('Colombo', DB::table('workspace_worker_requirements')->value('location'));
    }
}

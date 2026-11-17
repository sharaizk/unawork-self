<?php

namespace Tests\Feature\Matching;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/** UNA-016 migration: pod_matches.algorithm backfilled, required afterwards, reversible. In-memory SQLite only. */
class PodMatchAlgorithmMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATION = 'migrations/2026_11_19_000300_add_algorithm_to_pod_matches.php';

    public function test_existing_rows_are_deterministic_and_new_rows_must_name_an_algorithm(): void
    {
        $this->assertUsingInMemorySqlite();
        Schema::create('pod_matches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('pod_worker_requirement_id');
            $t->string('status', 20);
            $t->string('scoring_version', 32);
            $t->timestamp('evaluated_at');
        });
        DB::table('pod_matches')->insert([
            ['user_id' => 1, 'pod_worker_requirement_id' => 1, 'status' => 'eligible', 'scoring_version' => 'una-013.1', 'evaluated_at' => '2026-06-15 12:00:00'],
            ['user_id' => 2, 'pod_worker_requirement_id' => 1, 'status' => 'disqualified', 'scoring_version' => 'una-013.1', 'evaluated_at' => '2026-06-15 12:00:00'],
        ]);

        (require database_path(self::MIGRATION))->up();

        $this->assertSame(['deterministic', 'deterministic'], DB::table('pod_matches')->orderBy('id')->pluck('algorithm')->all());
        $this->assertSame(['una-013.1', '2026-06-15 12:00:00'], [DB::table('pod_matches')->value('scoring_version'), DB::table('pod_matches')->value('evaluated_at')], 'history untouched');
        try {
            DB::table('pod_matches')->insert(['user_id' => 3, 'pod_worker_requirement_id' => 1, 'status' => 'eligible', 'scoring_version' => 'v', 'evaluated_at' => '2026-06-15 12:00:00']);
            $this->fail('a row without an algorithm was stored');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        (require database_path(self::MIGRATION))->down();
        $this->assertFalse(Schema::hasColumn('pod_matches', 'algorithm'));
        $this->assertSame(2, DB::table('pod_matches')->count());
    }
}

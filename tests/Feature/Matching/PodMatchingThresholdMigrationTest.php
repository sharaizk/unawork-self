<?php

namespace Tests\Feature\Matching;

use App\Models\Pod;
use App\Models\Workspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/** UNA-014 migration on the pre-UNA-014 schema: defaults for existing rows, casts, reversible. In-memory SQLite only. */
class PodMatchingThresholdMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATION = 'migrations/2026_11_19_000200_add_matching_thresholds.php';

    public function test_existing_pods_get_the_default_threshold_and_down_reverses(): void
    {
        $this->assertUsingInMemorySqlite();
        Schema::create('pods', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->unsignedBigInteger('created_by');
            $t->string('status', 20)->default('draft');
            $t->string('matching_mode', 20)->nullable();
            $t->timestamps();
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('created_by');
            $t->string('matching_mode', 20)->nullable();
            $t->timestamps();
        });
        DB::table('pods')->insert([
            ['id' => 1, 'title' => 'Auto', 'created_by' => 1, 'status' => 'published', 'matching_mode' => 'automatic'],
            ['id' => 2, 'title' => 'Pending', 'created_by' => 1, 'status' => 'published', 'matching_mode' => 'pending_approval'],
        ]);
        DB::table('workspaces')->insert(['id' => 1, 'name' => 'Co', 'created_by' => 1, 'matching_mode' => 'manual']);
        $before = DB::table('pods')->orderBy('id')->get(['id', 'title', 'status', 'matching_mode'])->toArray();

        (require database_path(self::MIGRATION))->up();

        $this->assertSame([70, 70], DB::table('pods')->orderBy('id')->pluck('minimum_match_threshold')->map(fn ($v) => (int) $v)->all());
        $this->assertEquals($before, DB::table('pods')->orderBy('id')->get(['id', 'title', 'status', 'matching_mode'])->toArray(), 'existing modes untouched');
        $this->assertNull(DB::table('workspaces')->value('default_minimum_match_threshold'));

        DB::table('pods')->insert(['title' => 'New', 'created_by' => 1]);
        $this->assertSame(70, (int) DB::table('pods')->where('title', 'New')->value('minimum_match_threshold'), 'not null with a default');
        try {
            DB::table('pods')->insert(['title' => 'Null', 'created_by' => 1, 'minimum_match_threshold' => null]);
            $this->fail('null threshold stored');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        // Casts.
        $this->assertSame(70, Pod::find(1)->minimum_match_threshold);
        DB::table('workspaces')->where('id', 1)->update(['default_minimum_match_threshold' => '55']);
        $this->assertSame(55, Workspace::find(1)->default_minimum_match_threshold);

        (require database_path(self::MIGRATION))->down();
        $this->assertFalse(Schema::hasColumn('pods', 'minimum_match_threshold'));
        $this->assertFalse(Schema::hasColumn('workspaces', 'default_minimum_match_threshold'));
        $this->assertSame(['automatic', 'pending_approval', null], DB::table('pods')->orderBy('id')->pluck('matching_mode')->all());
    }
}

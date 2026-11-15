<?php

namespace Tests\Feature\Pod;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/** UNA-011 migration on the pre-UNA-011 schema: every existing POD stays public. In-memory SQLite only. */
class PodLifecycleMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATION = 'migrations/2026_11_18_000400_add_publication_lifecycle_to_pods.php';

    public function test_existing_pods_become_published_and_new_pods_default_to_draft(): void
    {
        $this->assertUsingInMemorySqlite();
        foreach (['job_titles' => 'title', 'skills' => 'name'] as $table => $column) {
            Schema::create($table, function (Blueprint $t) use ($column) {
                $t->id();
                $t->string($column);
                $t->timestamps();
            });
        }
        Schema::create('workspace_worker_requirements', fn (Blueprint $t) => $t->id());
        Schema::create('pods', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->boolean('inner_enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('pod_users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('pod_id');
            $t->string('state', 40);
        });
        DB::table('pods')->insert([
            ['id' => 3, 'title' => 'Old', 'created_by' => 1, 'created_at' => '2025-01-01 10:00:00', 'updated_at' => '2025-02-01 10:00:00'],
            ['id' => 7, 'title' => 'Older', 'created_by' => 2, 'created_at' => '2024-06-01 08:00:00', 'updated_at' => '2024-06-01 08:00:00'],
        ]);
        DB::table('pod_users')->insert(['user_id' => 1, 'pod_id' => 3, 'state' => 'inner_circle']);
        $members = DB::table('pod_users')->get()->toArray();

        (require database_path(self::MIGRATION))->up();

        $this->assertSame(
            [[3, 'Old', 'published', '2025-01-01 10:00:00'], [7, 'Older', 'published', '2024-06-01 08:00:00']],
            DB::table('pods')->orderBy('id')->get()->map(fn ($p) => [(int) $p->id, $p->title, $p->status, (string) $p->published_at])->all()
        );
        $this->assertEquals($members, DB::table('pod_users')->get()->toArray());
        $this->assertSame('2025-02-01 10:00:00', DB::table('pods')->where('id', 3)->value('updated_at'));

        DB::table('pods')->insert(['title' => 'New', 'created_by' => 1]);
        $this->assertSame(['draft', null], [DB::table('pods')->where('title', 'New')->value('status'), DB::table('pods')->where('title', 'New')->value('published_at')]);
        $this->assertTrue(Schema::hasIndex('pods', 'pods_status_index'));

        (require database_path(self::MIGRATION))->down();
        $this->assertFalse(Schema::hasColumn('pods', 'status'));
        $this->assertFalse(Schema::hasTable('pod_worker_requirements'));
        $this->assertSame(3, DB::table('pods')->count());
    }
}

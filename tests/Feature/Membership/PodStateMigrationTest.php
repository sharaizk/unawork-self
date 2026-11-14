<?php

namespace Tests\Feature\Membership;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-006: the real explicit-state migration run against a pre-UNA-006 schema (legacy
 * pod_users.status, UNA-004 unique pair). In-memory SQLite only.
 */
class PodStateMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATION = 'migrations/2026_11_16_000000_explicit_pod_membership_states.php';
    private object $migration;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createLegacySchema();
        $this->migration = require database_path(self::MIGRATION);
    }

    public function test_legacy_statuses_map_to_explicit_states_with_initial_history(): void
    {
        $this->seedLegacy();
        $before = DB::table('pod_users')->orderBy('id')->get(['id', 'user_id', 'pod_id', 'permission', 'created_at', 'updated_at'])->toArray();

        $this->migration->up();

        $expected = [
            'owner' => 'inner_circle',          // 1
            'applicant' => 'pending_business_approval', // 2
            'invitee' => 'matched',             // 3
            'inner' => 'inner_circle',          // 4
            'outer' => 'outer_circle',          // 5
            'declined' => 'rejected',           // 6
            'userPermissionOnly' => 'outer_circle', // 7: projects_index on the User model does not count
            'noWorkspaceMembership' => 'outer_circle', // 8: conservative fallback
            'otherPodOwner' => 'inner_circle',  // owner detected through pods.created_by
        ];
        foreach ($expected as $key => $state) {
            $this->assertSame($state, DB::table('pod_users')->where('id', $this->ids[$key])->value('state'), $key);
        }

        // 10. every row has a valid state; 11. exactly one initial history row each
        $valid = array_map(fn ($s) => $s->value, \App\Enums\PodUser\State::cases());
        $this->assertSame(0, DB::table('pod_users')->whereNull('state')->orWhereNotIn('state', $valid)->count());
        foreach (DB::table('pod_users')->get() as $row) {
            $history = DB::table('pod_user_state_histories')->where('pod_user_id', $row->id)->get();
            $this->assertCount(1, $history);
            $this->assertNull($history[0]->from_state);
            $this->assertSame($row->state, $history[0]->to_state);
            $this->assertNull($history[0]->actor_id);
        }

        // 12. ids, timestamps and UNA-004 uniqueness intact; the legacy column is gone
        $this->assertEquals($before, DB::table('pod_users')->orderBy('id')->get(['id', 'user_id', 'pod_id', 'permission', 'created_at', 'updated_at'])->toArray());
        $this->assertFalse(Schema::hasColumn('pod_users', 'status'));
        $this->assertTrue(Schema::hasIndex('pod_users', 'pod_users_user_id_pod_id_unique', 'unique'));
        try {
            DB::table('pod_users')->insert(['user_id' => 2, 'pod_id' => 1, 'permission' => 'Member', 'state' => 'matched']);
            $this->fail('duplicate pair accepted');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }

        // 13. new indexes; workspace permissions untouched
        $this->assertTrue(Schema::hasIndex('pod_users', 'pod_users_pod_id_state_index'));
        $this->assertTrue(Schema::hasIndex('pod_users', 'pod_users_user_id_state_index'));
        $this->assertSame(3, DB::table('model_has_permissions')->count());
    }

    public function test_unknown_legacy_status_aborts_before_any_change(): void // 9
    {
        $this->seedLegacy();
        DB::table('pod_users')->where('id', $this->ids['outer'])->update(['status' => 'archived']);

        try {
            $this->migration->up();
            $this->fail('expected the migration to refuse an unknown status');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('archived', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('pod_users', 'status'));
        $this->assertFalse(Schema::hasColumn('pod_users', 'state'));
        $this->assertFalse(Schema::hasTable('pod_user_state_histories'));
    }

    public function test_down_collapses_states_deterministically_and_keeps_rows(): void // 14
    {
        $this->seedLegacy();
        $this->migration->up();
        // Richer states that only exist after UNA-006.
        DB::table('pod_users')->where('id', $this->ids['outer'])->update(['state' => 'screening_in_progress']);
        DB::table('pod_users')->where('id', $this->ids['inner'])->update(['state' => 'suspended']);
        DB::table('pod_users')->where('id', $this->ids['noWorkspaceMembership'])->update(['state' => 'removed']);
        $count = DB::table('pod_users')->count();

        $this->migration->down();

        $expected = [
            'owner' => 'active', 'applicant' => 'pod_pending', 'invitee' => 'employee_pending', 'declined' => 'declined',
            'outer' => 'active', 'inner' => 'declined', 'noWorkspaceMembership' => 'declined',
        ];
        foreach ($expected as $key => $status) {
            $this->assertSame($status, DB::table('pod_users')->where('id', $this->ids[$key])->value('status'), $key);
        }
        $this->assertSame($count, DB::table('pod_users')->count());
        $this->assertFalse(Schema::hasColumn('pod_users', 'state'));
        $this->assertFalse(Schema::hasTable('pod_user_state_histories'));
        $this->assertTrue(Schema::hasIndex('pod_users', 'pod_users_user_id_pod_id_unique', 'unique'));
    }

    // ------------------------------------------------------------------ fixture

    private function seedLegacy(): void
    {
        foreach (range(1, 12) as $id) {
            DB::table('users')->insert(['id' => $id, 'name' => "u{$id}", 'email' => "u{$id}@example.test"]);
        }
        DB::table('pods')->insert([['id' => 1, 'title' => 'Pod', 'created_by' => 1], ['id' => 2, 'title' => 'Other', 'created_by' => 9]]);
        DB::table('workspaces')->insert([
            ['id' => 10, 'name' => 'Pod WS', 'created_by' => 1, 'ownerable_type' => 'App\\Models\\Pod', 'ownerable_id' => 1],
            ['id' => 20, 'name' => 'Other WS', 'created_by' => 9, 'ownerable_type' => 'App\\Models\\Pod', 'ownerable_id' => 2],
        ]);
        $projectsIndex = DB::table('permissions')->insertGetId(['name' => 'projects_index', 'guard_name' => 'web']);

        $rows = [
            'owner' => [1, 1, 'Owner', 'active'],
            'applicant' => [2, 1, 'Member', 'pod_pending'],
            'invitee' => [3, 1, 'Member', 'employee_pending'],
            'inner' => [4, 1, 'Member', 'active'],
            'outer' => [5, 1, 'Member', 'active'],
            'declined' => [6, 1, 'Member', 'declined'],
            'userPermissionOnly' => [7, 1, 'Member', 'active'],
            'noWorkspaceMembership' => [8, 1, 'Member', 'active'],
            'otherPodOwner' => [9, 2, 'Member', 'active'],
        ];
        foreach ($rows as $key => [$user, $pod, $permission, $status]) {
            $this->ids[$key] = DB::table('pod_users')->insertGetId([
                'user_id' => $user, 'pod_id' => $pod, 'permission' => $permission, 'status' => $status,
                'created_at' => '2026-01-0' . min($user, 9) . ' 00:00:00', 'updated_at' => '2026-02-01 00:00:00',
            ]);
        }
        foreach ([1, 4, 5, 7] as $user) {
            $uw = DB::table('user_workspaces')->insertGetId(['user_id' => $user, 'workspace_id' => 10, 'permission' => 'Member']);
            if ($user === 4) {
                DB::table('model_has_permissions')->insert(['permission_id' => $projectsIndex, 'model_type' => 'App\\Models\\UserWorkspace', 'model_id' => $uw]);
            }
            if ($user === 7) {
                // projects_index on the User model with a colliding id, and on a different model id
                DB::table('model_has_permissions')->insert(['permission_id' => $projectsIndex, 'model_type' => 'App\\Models\\User', 'model_id' => $uw]);
                DB::table('model_has_permissions')->insert(['permission_id' => $projectsIndex, 'model_type' => 'App\\Models\\User', 'model_id' => 7]);
            }
        }
    }

    /** The schema as it was after UNA-004 and before UNA-006. */
    private function createLegacySchema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
        });
        Schema::create('pods', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->unsignedBigInteger('created_by');
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('created_by');
            $t->string('ownerable_type')->nullable();
            $t->unsignedBigInteger('ownerable_id')->nullable();
        });
        Schema::create('user_workspaces', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->integer('workspace_id');
            $t->text('permission');
            $t->string('status')->default('active');
        });
        Schema::create('pod_users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('pod_id');
            $t->string('permission');
            $t->string('status');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->unique(['user_id', 'pod_id'], 'pod_users_user_id_pod_id_unique');
        });
        $this->createPermissionSchema();
    }
}

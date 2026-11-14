<?php

namespace Tests\Feature\Membership;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-004: runs the real membership-constraint migration against pre-constraint tables full of
 * conflicting duplicates (in-memory SQLite only).
 */
class MembershipConstraintsMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATION = 'migrations/2026_11_15_000000_add_unique_membership_constraints.php';
    private const WS = 'App\\Models\\UserWorkspace';
    private const INDEXES = [
        'pod_users' => 'pod_users_user_id_pod_id_unique',
        'user_workspaces' => 'user_workspaces_user_id_workspace_id_unique',
        'user_projects' => 'user_projects_user_id_project_id_unique',
    ];

    private object $migration;
    private array $permissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createPreConstraintSchema();
        $this->migration = require database_path(self::MIGRATION);
    }

    public function test_duplicates_are_consolidated_by_the_documented_rules(): void
    {
        $this->seedFixture();

        $this->migration->up();

        // 1. exactly one row per pair; 14. nothing left to violate the index.
        foreach (['pod_users' => 'pod_id', 'user_workspaces' => 'workspace_id', 'user_projects' => 'project_id'] as $table => $parent) {
            $this->assertSame(0, DB::table($table)->select('user_id', $parent)->groupBy('user_id', $parent)->havingRaw('COUNT(*) > 1')->count(), $table);
        }
        $this->assertSame(['duplicate_groups' => 6, 'rows_removed' => 9], array_intersect_key($this->migration->summary['pod_users'], array_flip(['duplicate_groups', 'rows_removed'])));
        $this->assertSame(3, $this->migration->summary['user_workspaces']['duplicate_groups']);
        $this->assertSame(4, $this->migration->summary['user_projects']['duplicate_groups']);

        // pod_users ------------------------------------------------------------------
        $this->assertPod(1, 1, ['id' => 2, 'permission' => 'Owner', 'status' => 'active']);           // 3. owner wins, never downgraded
        $this->assertPod(2, 1, ['id' => 5, 'status' => 'active']);                                     // 4. active beats a newer pending row
        $this->assertPod(3, 1, ['id' => 7, 'status' => 'pod_pending', 'reason' => null]);              // 5. most recent state wins
        $this->assertPod(4, 1, ['id' => 9, 'status' => 'declined', 'reason' => 'Not a fit']);         // lowest declined id, newest reason
        $this->assertPod(5, 1, ['id' => 12, 'status' => 'employee_pending']);                          // directions are not blended
        $this->assertPod(6, 1, ['id' => 14, 'status' => 'pod_pending']);                               // null timestamps: id tiebreak, lowest id kept
        $owner = DB::table('pod_users')->find(2);
        $this->assertSame('2026-01-01 00:00:00', $owner->created_at);                                 // earliest created_at
        $this->assertSame('2026-03-01 00:00:00', $owner->updated_at);                                 // latest updated_at
        $this->assertEquals($this->unique['pod_users'], DB::table('pod_users')->find(16));            // 2. non-duplicates unchanged

        // user_workspaces ------------------------------------------------------------------
        $this->assertWorkspace(1, 1, ['id' => 2, 'permission' => 'Owner', 'status' => 'active', 'is_active' => 1]); // 6.
        $this->assertWorkspace(2, 1, ['id' => 5, 'permission' => 'Member', 'status' => 'active', 'is_active' => 1]);
        $this->assertWorkspace(3, 1, ['id' => 8, 'status' => 'declined', 'is_active' => 0]);         // 7. is_active reconciled
        $this->assertEqualsCanonicalizing(['news_index', 'users_edit', 'projects_index'], $this->workspacePermissions(2)); // 8. union
        $this->assertEqualsCanonicalizing(['users_index', 'news_edit'], $this->workspacePermissions(5));
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_type', self::WS)->whereIn('model_id', [1, 3, 4, 6, 7])->count());
        $this->assertTrue(DB::table('model_has_roles')->where('model_type', self::WS)->where('model_id', 2)->exists()); // role moved
        // 9. same numeric ids on another model type are untouched
        $this->assertSame(2, DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->whereIn('model_id', [1, 3])->count());
        $this->assertEquals($this->unique['user_workspaces'], DB::table('user_workspaces')->find(9));
        $this->assertEqualsCanonicalizing(['zoom_meeting_index'], $this->workspacePermissions(9));

        // user_projects ------------------------------------------------------------------
        $this->assertProject(1, 1, ['id' => 2, 'status' => 'active', 'is_active' => 1, 'permission' => '{"a":1}']); // 10.
        $this->assertProject(2, 1, ['id' => 5, 'status' => 'declined', 'is_active' => 0, 'permission' => '["x"]']); // 11.
        $this->assertProject(3, 1, ['id' => 6, 'status' => 'pending', 'is_active' => 0, 'permission' => '["legacy"]']); // 12.
        $this->assertProject(4, 1, ['id' => 8, 'status' => 'active', 'is_active' => 0]);              // plan-disabled stays disabled
        $this->assertEquals($this->unique['user_projects'], DB::table('user_projects')->find(10));

        // 13. notifications -----------------------------------------------------------------
        $this->assertNull(DB::table('notifications')->find(1), 'pod_request for an already-active member is obsolete');
        $this->assertNotification(2, 2);                                                                 // repointed
        $this->assertNull(DB::table('notifications')->find(3), 'pod_request whose survivor is an invitation is obsolete');
        $this->assertNotification(4, 14);
        $this->assertNull(DB::table('notifications')->find(6), 'workspace_join for a declined membership is obsolete');
        $this->assertNull(DB::table('notifications')->find(7), 'project_request for a declined claim is obsolete');
        $this->assertNull(DB::table('notifications')->find(8), 'project_request for an active member is obsolete');
        $this->assertNotification(9, 5);
        $this->assertEquals($this->notifications[10], DB::table('notifications')->find(10), 'other notification types are untouched');
        $this->assertEquals($this->notifications[11], DB::table('notifications')->find(11), 'payloads of another membership are untouched');
        $this->assertNotification(12, 5);
        $this->assertNull(DB::table('notifications')->find(13), 'a repointed duplicate of an existing card is removed');
        $this->assertNotification(14, 2, ['extra' => 'kept']);                                           // rest of payload preserved
    }

    public function test_unique_indexes_are_created_and_enforced_then_removed_by_down(): void
    {
        $this->seedFixture();
        $this->migration->up();

        // 15. indexes exist; 16. a direct duplicate insert fails.
        foreach (self::INDEXES as $table => $index) {
            $this->assertTrue(Schema::hasIndex($table, $index, 'unique'), $index);
        }
        foreach ([['pod_users', ['user_id' => 1, 'pod_id' => 1, 'permission' => 'Member', 'status' => 'active']],
                  ['user_workspaces', ['user_id' => 1, 'workspace_id' => 1, 'permission' => 'Member']],
                  ['user_projects', ['user_id' => 1, 'project_id' => 1]]] as [$table, $row]) {
            try {
                DB::table($table)->insert($row);
                $this->fail("{$table} accepted a duplicate pair");
            } catch (UniqueConstraintViolationException) {
                $this->addToAssertionCount(1);
            }
        }

        $counts = array_map(fn ($table) => DB::table($table)->count(), array_combine(array_keys(self::INDEXES), array_keys(self::INDEXES)));

        $this->migration->down();

        // 17. indexes gone, memberships kept; 18. uniqueness no longer enforced.
        foreach (self::INDEXES as $table => $index) {
            $this->assertFalse(Schema::hasIndex($table, $index), $index);
            $this->assertSame($counts[$table], DB::table($table)->count());
        }
        // The user_id foreign keys keep a supporting index after the composite one is dropped.
        $this->assertTrue(Schema::hasIndex('pod_users', ['user_id']));
        $this->assertTrue(Schema::hasIndex('user_projects', ['user_id']));
        DB::table('user_projects')->insert(['user_id' => 1, 'project_id' => 1]);
        $this->assertSame(2, DB::table('user_projects')->where('user_id', 1)->where('project_id', 1)->count());
    }

    public function test_up_is_idempotent_and_handles_many_groups_in_batches(): void
    {
        $rows = [];
        for ($user = 1; $user <= 450; $user++) {
            $rows[] = ['user_id' => $user, 'project_id' => 1, 'status' => 'pending', 'is_active' => 0, 'updated_at' => null];
            $rows[] = ['user_id' => $user, 'project_id' => 1, 'status' => 'declined', 'is_active' => 0, 'updated_at' => '2026-05-01 00:00:00'];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('user_projects')->insert($chunk);
        }

        $this->migration->up();
        $this->assertSame(450, $this->migration->summary['user_projects']['duplicate_groups']);
        $this->assertSame(450, DB::table('user_projects')->where('status', 'declined')->count());

        $again = require database_path(self::MIGRATION);
        $again->up(); // indexes exist: nothing to do
        $this->assertSame([], $again->summary);
        $this->assertSame(450, DB::table('user_projects')->count());
    }

    public function test_ambiguous_groups_abort_before_any_index_is_created(): void
    {
        DB::table('pod_users')->insert([
            ['user_id' => 1, 'pod_id' => 1, 'permission' => 'Member', 'status' => 'suspended'],
            ['user_id' => 1, 'pod_id' => 1, 'permission' => 'Member', 'status' => 'active'],
        ]);

        try {
            $this->migration->up();
            $this->fail('expected the migration to refuse an ambiguous group');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('conflicting unknown statuses', $e->getMessage());
        }

        $this->assertSame(2, DB::table('pod_users')->count(), 'nothing was deleted');
        $this->assertFalse(Schema::hasIndex('pod_users', self::INDEXES['pod_users']));
    }

    // ------------------------------------------------------------------------- fixture

    private array $unique = [];
    private array $notifications = [];

    private function seedFixture(): void
    {
        $t = fn (string $day) => "2026-{$day} 00:00:00";

        // pod_users -- ids 1..16 (16 is not a duplicate)
        $this->insert('pod_users', [
            [1, 1, 1, 'Member', 'pod_pending', null, $t('02-01'), $t('02-01')],
            [2, 1, 1, 'Owner', 'active', null, $t('01-01'), $t('01-15')],
            [3, 1, 1, 'Member', 'declined', 'x', $t('03-01'), $t('03-01')],
            [4, 2, 1, 'Member', 'employee_pending', null, $t('02-01'), $t('02-10')],
            [5, 2, 1, 'Member', 'active', null, $t('01-01'), $t('01-01')],
            [6, 3, 1, 'Member', 'declined', 'old', $t('01-01'), $t('01-10')],
            [7, 3, 1, 'Member', 'pod_pending', null, $t('01-01'), $t('03-01')],
            [8, 3, 1, 'Member', 'declined', 'newer', $t('01-01'), $t('02-01')],
            [9, 4, 1, 'Member', 'declined', null, $t('01-01'), $t('01-01')],
            [10, 4, 1, 'Member', 'declined', 'Not a fit', $t('01-01'), $t('03-01')],
            [11, 4, 1, 'Member', 'employee_pending', null, $t('01-01'), $t('02-01')],
            [12, 5, 1, 'Member', 'employee_pending', null, $t('01-01'), $t('02-01')],
            [13, 5, 1, 'Member', 'pod_pending', null, $t('01-01'), $t('01-01')],
            [14, 6, 1, 'Member', 'pod_pending', null, null, null],
            [15, 6, 1, 'Member', 'pod_pending', null, null, null],
            [16, 1, 2, 'Member', 'active', null, $t('01-01'), $t('01-01')],
        ], ['id', 'user_id', 'pod_id', 'permission', 'status', 'reason', 'created_at', 'updated_at']);

        // user_workspaces -- ids 1..9 (9 is not a duplicate)
        $this->insert('user_workspaces', [
            [1, 1, 1, 'Member', 'active', 0, $t('01-01'), $t('01-01')],
            [2, 1, 1, 'Owner', 'active', 1, $t('01-01'), $t('01-01')],
            [3, 1, 1, 'Member', 'employee_pending', 1, $t('01-01'), $t('02-01')],
            [4, 2, 1, 'Member', 'declined', 0, $t('01-01'), $t('03-01')],
            [5, 2, 1, 'Member', 'active', 1, $t('01-01'), $t('02-01')],
            [6, 2, 1, 'Member', 'active', 1, $t('01-01'), $t('01-01')],
            [7, 3, 1, 'Member', 'workspace_pending', 1, $t('01-01'), $t('01-01')],
            [8, 3, 1, 'Member', 'declined', 1, $t('01-01'), $t('02-01')],
            [9, 4, 1, 'Member', 'active', 1, $t('01-01'), $t('01-01')],
        ], ['id', 'user_id', 'workspace_id', 'permission', 'status', 'is_active', 'created_at', 'updated_at']);
        foreach (['users_index', 'users_edit', 'news_index', 'news_edit', 'projects_index', 'zoom_meeting_index'] as $name) {
            $this->permissionIds[$name] = DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web']);
        }
        $grant = fn (string $type, int $id, string $name) => DB::table('model_has_permissions')->insert(['permission_id' => $this->permissionIds[$name], 'model_type' => $type, 'model_id' => $id]);
        $grant(self::WS, 1, 'users_edit');
        $grant(self::WS, 2, 'news_index');
        $grant(self::WS, 3, 'projects_index');
        $grant(self::WS, 3, 'news_index'); // already on the survivor: no duplicate row
        $grant(self::WS, 4, 'news_edit');
        $grant(self::WS, 5, 'users_index');
        $grant(self::WS, 9, 'zoom_meeting_index');
        $grant('App\\Models\\User', 1, 'users_edit');       // unrelated model, colliding ids
        $grant('App\\Models\\User', 3, 'projects_index');
        $auditor = DB::table('roles')->insertGetId(['name' => 'auditor', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $auditor, 'model_type' => self::WS, 'model_id' => 3]);

        // user_projects -- ids 1..10 (10 is not a duplicate)
        $this->insert('user_projects', [
            [1, 1, 1, 'pending', 0, null, $t('01-01'), $t('02-01')],
            [2, 1, 1, 'active', 1, '{"a":1}', $t('01-01'), $t('01-01')],
            [3, 1, 1, 'declined', 0, null, $t('01-01'), $t('03-01')],
            [4, 2, 1, 'pending', 0, null, $t('01-01'), $t('01-01')],
            [5, 2, 1, 'declined', 0, '["x"]', $t('01-01'), $t('02-01')],
            [6, 3, 1, 'pending', 0, null, $t('01-01'), $t('02-01')],
            [7, 3, 1, 'declined', 0, '["legacy"]', $t('01-01'), $t('01-01')],
            [8, 4, 1, 'active', 0, null, $t('01-01'), $t('01-01')],
            [9, 4, 1, 'pending', 0, null, $t('01-01'), $t('02-01')],
            [10, 5, 1, 'pending', 0, null, $t('01-01'), $t('01-01')],
        ], ['id', 'user_id', 'project_id', 'status', 'is_active', 'permission', 'created_at', 'updated_at']);

        $this->unique = [
            'pod_users' => DB::table('pod_users')->find(16),
            'user_workspaces' => DB::table('user_workspaces')->find(9),
            'user_projects' => DB::table('user_projects')->find(10),
        ];

        // notifications: [id, recipient, type, payload]
        $pod = fn (int $id, int $user) => ['id' => $id, 'user_id' => $user, 'pod_id' => 1];
        $ws = fn (int $id, int $user) => ['id' => $id, 'user_id' => $user, 'workspace_id' => 1];
        $proj = fn (int $id, int $user) => ['id' => $id, 'user_id' => $user, 'project_id' => 1];
        foreach ([
            [1, 100, 'pod_request', $pod(1, 1)],
            [2, 1, 'pod_request_accept_user', $pod(1, 1)],
            [3, 100, 'pod_request', $pod(13, 5)],
            [4, 100, 'pod_request', $pod(15, 6)],
            [6, 100, 'workspace_join', $ws(7, 3)],
            [7, 100, 'project_request', $proj(4, 2)],
            [8, 100, 'project_request', $proj(9, 4)],
            [9, 100, 'workspace_request_accept_owner', $ws(6, 2)],
            [10, 1, 'project_assign', ['id' => 1, 'name' => 'Project']],
            [11, 100, 'pod_request', ['id' => 1, 'user_id' => 999, 'pod_id' => 1]],
            [12, 2, 'workspace_request_accept_user', $ws(5, 2)],
            [13, 2, 'workspace_request_accept_user', $ws(4, 2)],
            [14, 100, 'pod_request_accept_owner', $pod(3, 1) + ['extra' => 'kept']],
        ] as [$id, $recipient, $type, $payload]) {
            DB::table('notifications')->insert(['id' => $id, 'user_id' => $recipient, 'type' => $type, 'related_id' => $payload['id'], 'data' => json_encode($payload), 'is_read' => 0]);
            $this->notifications[$id] = DB::table('notifications')->find($id);
        }
    }

    private function insert(string $table, array $rows, array $columns): void
    {
        foreach ($rows as $row) {
            DB::table($table)->insert(array_combine($columns, $row));
        }
    }

    private function assertPod(int $user, int $pod, array $expected): void
    {
        $this->assertMembership('pod_users', 'pod_id', $user, $pod, $expected);
    }

    private function assertWorkspace(int $user, int $workspace, array $expected): void
    {
        $this->assertMembership('user_workspaces', 'workspace_id', $user, $workspace, $expected);
    }

    private function assertProject(int $user, int $project, array $expected): void
    {
        $this->assertMembership('user_projects', 'project_id', $user, $project, $expected);
    }

    private function assertMembership(string $table, string $parent, int $user, int $parentId, array $expected): void
    {
        $rows = DB::table($table)->where('user_id', $user)->where($parent, $parentId)->get();
        $this->assertCount(1, $rows, "{$table} ({$user}, {$parentId})");
        foreach ($expected as $column => $value) {
            $this->assertEquals($value, $rows->first()->{$column}, "{$table} ({$user}, {$parentId}).{$column}");
        }
    }

    private function assertNotification(int $id, int $membershipId, array $extra = []): void
    {
        $notification = DB::table('notifications')->find($id);
        $this->assertNotNull($notification, "notification {$id} was removed");
        $data = json_decode($notification->data, true);
        $this->assertSame($membershipId, $data['id']);
        $this->assertSame($membershipId, (int) $notification->related_id);
        foreach ($extra as $key => $value) {
            $this->assertSame($value, $data[$key]);
        }
    }

    private function workspacePermissions(int $userWorkspaceId): array
    {
        return DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_type', self::WS)->where('model_id', $userWorkspaceId)
            ->pluck('permissions.name')->all();
    }

    /** Production-shaped membership tables as they were BEFORE UNA-004 (no unique pairs). */
    private function createPreConstraintSchema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
        });
        DB::table('users')->insert(array_map(fn ($id) => ['id' => $id], array_merge(range(1, 460), [999])));

        Schema::create('pod_users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('pod_id');
            $t->string('permission');
            $t->string('status');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::create('user_workspaces', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('user_id');
            $t->integer('workspace_id');
            $t->text('permission');
            $t->integer('is_active')->default(1);
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('user_projects', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('project_id');
            $t->integer('is_active')->default(1);
            $t->text('permission')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
        $this->createPermissionSchema();
    }
}

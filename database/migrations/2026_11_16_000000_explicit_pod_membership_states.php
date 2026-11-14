<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-006: replace pod_users.status (legacy workflow) with pod_users.state (explicit workflow and
 * circle state) and add the append-only pod_user_state_histories table.
 *
 * Backfill (first matching rule wins):
 *   owner row (permission 'Owner' or pods.created_by)            -> inner_circle
 *   pod_pending                                                  -> pending_business_approval
 *   employee_pending                                             -> matched
 *   declined                                                     -> rejected
 *   active + projects_index (web) on THAT membership's UserWorkspace -> inner_circle
 *   active otherwise                                             -> outer_circle
 * Conservative fallback: an active member whose POD workspace or workspace membership is missing
 * becomes outer_circle (Inner Circle is never granted without evidence). Only permissions assigned
 * to App\Models\UserWorkspace for that exact row count. Any other status aborts before any change.
 * Reading projects_index here is a one-time backfill; runtime code never derives state from it.
 *
 * Every membership gets one initial history row (from_state null, system actor).
 *
 * down(): matched -> employee_pending, pending_business_approval -> pod_pending, the five active
 * circle/screening states -> active, rejected/suspended/removed -> declined (no access in legacy
 * terms); the state column, its indexes and the history table are dropped. Rows and ids are kept.
 */
return new class extends Migration
{
    private const CHUNK = 500;
    private const POD_MORPH = 'App\\Models\\Pod';
    private const WORKSPACE_MEMBERSHIP_MORPH = 'App\\Models\\UserWorkspace';
    private const HISTORY = 'pod_user_state_histories';

    private const LEGACY_TO_STATE = [
        'pod_pending' => 'pending_business_approval',
        'employee_pending' => 'matched',
        'declined' => 'rejected',
        'active' => null, // circle decided from the membership's projects_index
    ];

    private const STATE_TO_LEGACY = [
        'matched' => 'employee_pending',
        'pending_business_approval' => 'pod_pending',
        'outer_circle' => 'active',
        'screening_in_progress' => 'active',
        'screening_completed' => 'active',
        'pending_inner_approval' => 'active',
        'inner_circle' => 'active',
        'rejected' => 'declined',
        'suspended' => 'declined',
        'removed' => 'declined',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('pod_users', 'status')) {
            return; // already converted
        }

        $unknown = DB::table('pod_users')->whereNotIn('status', array_keys(self::LEGACY_TO_STATE))->distinct()->pluck('status');
        if ($unknown->isNotEmpty()) {
            throw new RuntimeException('UNA-006: unknown pod_users.status values [' . $unknown->implode(', ') . ']; refusing to guess a state.');
        }

        if (!Schema::hasColumn('pod_users', 'state')) {
            Schema::table('pod_users', fn (Blueprint $table) => $table->string('state', 40)->nullable()->after('permission'));
        }

        DB::transaction(function () {
            $projectsIndex = DB::table('permissions')->where('name', 'projects_index')->where('guard_name', 'web')->pluck('id')->all();

            DB::table('pod_users')->select(['id', 'user_id', 'pod_id', 'permission', 'status'])
                ->chunkById(self::CHUNK, function (Collection $rows) use ($projectsIndex) {
                    $this->backfill($rows, $projectsIndex);
                });
        });

        Schema::table('pod_users', function (Blueprint $table) {
            $table->string('state', 40)->nullable(false)->change();
            $table->index(['pod_id', 'state'], 'pod_users_pod_id_state_index');
            $table->index(['user_id', 'state'], 'pod_users_user_id_state_index');
        });
        Schema::table('pod_users', fn (Blueprint $table) => $table->dropColumn('status'));

        // Created only after every pod_users change: SQLite rebuilds a table to alter a column, and a
        // rebuild would cascade into rows that already reference it.
        if (!Schema::hasTable(self::HISTORY)) {
            Schema::create(self::HISTORY, function (Blueprint $table) {
                $table->id();
                $table->foreignId('pod_user_id')->constrained('pod_users')->cascadeOnDelete();
                $table->string('from_state', 40)->nullable();
                $table->string('to_state', 40);
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['pod_user_id', 'id']);
            });
        }

        // One initial history row per membership (system actor), set-based.
        DB::statement(
            'INSERT INTO ' . self::HISTORY . ' (pod_user_id, from_state, to_state, actor_id, reason, created_at) '
            . 'SELECT id, NULL, state, NULL, ?, ? FROM pod_users',
            ['Initial state backfilled from the legacy status (UNA-006)', now()->toDateTimeString()]
        );
    }

    public function down(): void
    {
        if (!Schema::hasColumn('pod_users', 'state')) {
            return;
        }

        Schema::dropIfExists(self::HISTORY);
        Schema::table('pod_users', fn (Blueprint $table) => $table->string('status')->nullable()->after('permission'));

        foreach (self::STATE_TO_LEGACY as $state => $legacy) {
            DB::table('pod_users')->where('state', $state)->update(['status' => $legacy]);
        }
        if (DB::table('pod_users')->whereNull('status')->exists()) {
            throw new RuntimeException('UNA-006 rollback: pod_users rows with an unmappable state.');
        }

        Schema::table('pod_users', function (Blueprint $table) {
            $table->string('status')->nullable(false)->change();
            $table->dropIndex('pod_users_pod_id_state_index');
            $table->dropIndex('pod_users_user_id_state_index');
        });
        Schema::table('pod_users', fn (Blueprint $table) => $table->dropColumn('state'));
    }

    private function backfill(Collection $rows, array $projectsIndex): void
    {
        $podIds = $rows->pluck('pod_id')->unique()->all();
        $owners = DB::table('pods')->whereIn('id', $podIds)->pluck('created_by', 'id');
        $workspaces = DB::table('workspaces')->where('ownerable_type', self::POD_MORPH)->whereIn('ownerable_id', $podIds)->pluck('id', 'ownerable_id');

        $memberships = DB::table('user_workspaces')
            ->whereIn('workspace_id', $workspaces->values()->all() ?: [0])
            ->whereIn('user_id', $rows->pluck('user_id')->unique()->all())
            ->get(['id', 'user_id', 'workspace_id'])
            ->keyBy(fn ($m) => $m->user_id . ':' . $m->workspace_id);

        $innerMembershipIds = $projectsIndex
            ? DB::table('model_has_permissions')
                ->where('model_type', self::WORKSPACE_MEMBERSHIP_MORPH)
                ->whereIn('permission_id', $projectsIndex)
                ->whereIn('model_id', $memberships->pluck('id')->all() ?: [0])
                ->pluck('model_id')->map(fn ($id) => (int) $id)->flip()
            : collect();

        foreach ($rows as $row) {
            $isOwner = $row->permission === 'Owner' || (int) ($owners[$row->pod_id] ?? 0) === (int) $row->user_id;

            $state = match (true) {
                $isOwner => 'inner_circle',
                self::LEGACY_TO_STATE[$row->status] !== null => self::LEGACY_TO_STATE[$row->status],
                default => $this->activeCircle($row, $workspaces, $memberships, $innerMembershipIds),
            };

            DB::table('pod_users')->where('id', $row->id)->update(['state' => $state]);
        }
    }

    private function activeCircle(object $row, Collection $workspaces, Collection $memberships, Collection $innerMembershipIds): string
    {
        $workspaceId = $workspaces[$row->pod_id] ?? null;
        $membership = $workspaceId ? $memberships->get($row->user_id . ':' . $workspaceId) : null;

        return $membership && $innerMembershipIds->has((int) $membership->id) ? 'inner_circle' : 'outer_circle';
    }
};

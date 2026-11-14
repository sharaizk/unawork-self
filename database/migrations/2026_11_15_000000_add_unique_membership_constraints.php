<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-004: one membership row per (user, pod|workspace|project), enforced by the database.
 *
 * For each table: consolidate existing duplicate pairs, verify none remain, then add the
 * composite unique index. Only duplicate groups are queried, in batches of BATCH groups,
 * each batch in its own transaction (the DDL runs afterwards, outside it).
 *
 * Survivor / merge policy (see resolve* methods; created_at = earliest, updated_at = latest):
 *   pod_users        Owner row > active row > most recent state (updated_at, then id).
 *                    Survivor is the lowest id holding that state (ids live in notification JSON).
 *                    A declined survivor keeps the most recent non-empty decline reason.
 *                    pod_pending and employee_pending are never blended: the most recent wins.
 *   user_workspaces  Owner > active (is_active=1 first) > most recent state. is_active is
 *                    reconciled with the status (declined => 0). Spatie permissions and roles of
 *                    the removed rows are unioned onto the survivor (exact model_type only).
 *   user_projects    active (is_active=1 first) > most recent pending/declined (is_active=0).
 *                    Keeps the survivor's legacy permission JSON, else the most recent non-null one.
 * A group whose rows disagree on an unknown status/permission value aborts the migration.
 *
 * Notifications that embed a removed row id (data.id and related_id) for that membership type
 * and shape (same user and parent) are repointed to the survivor. An action notification whose
 * action no longer applies to the merged state (e.g. a join request whose survivor is already
 * active) is removed, exactly as the app removes it once the request is handled; a repointed
 * notification that duplicates one the recipient already has for the survivor is removed too.
 *
 * down() drops the three unique indexes only. Consolidated duplicates are intentionally NOT
 * recreated: they were corrupt state and reintroducing them would be unsafe.
 */
return new class extends Migration
{
    private const BATCH = 200;
    private const WORKSPACE_MORPH = 'App\\Models\\UserWorkspace';

    private const TABLES = [
        'pod_users' => ['parent' => 'pod_id', 'index' => 'pod_users_user_id_pod_id_unique'],
        'user_workspaces' => ['parent' => 'workspace_id', 'index' => 'user_workspaces_user_id_workspace_id_unique'],
        'user_projects' => ['parent' => 'project_id', 'index' => 'user_projects_user_id_project_id_unique'],
    ];

    /** Notification types whose payload is a row of the table (data.id / related_id = row id). */
    private const NOTIFICATION_TYPES = [
        'pod_users' => ['pod_request', 'pod_request_accept_owner', 'pod_request_accept_user', 'pod_request_decline_owner', 'pod_request_decline_user'],
        'user_workspaces' => ['workspace_invite', 'workspace_join', 'workspace_request_accept_owner', 'workspace_request_accept_user', 'workspace_request_decline_owner', 'workspace_request_decline_user'],
        'user_projects' => ['project_request'],
    ];

    /** Action notifications and the membership status their accept/decline buttons require. */
    private const ACTION_NOTIFICATIONS = [
        'pod_request' => 'pod_pending',
        'workspace_join' => 'workspace_pending',
        'workspace_invite' => 'employee_pending',
        'project_request' => 'pending',
    ];

    private const KNOWN_STATUSES = [
        'pod_users' => ['active', 'pod_pending', 'employee_pending', 'declined'],
        'user_workspaces' => ['active', 'workspace_pending', 'employee_pending', 'declined'],
        'user_projects' => ['active', 'pending', 'declined'],
    ];

    /** Per-table counts (no user data), also written to the log. */
    public array $summary = [];

    public function up(): void
    {
        foreach (self::TABLES as $table => $config) {
            if (Schema::hasIndex($table, $config['index'])) {
                continue; // already constrained
            }

            $this->summary[$table] = ['duplicate_groups' => 0, 'rows_removed' => 0, 'notifications_repointed' => 0, 'notifications_removed' => 0];
            $this->consolidate($table, $config['parent']);
            $this->assertNoDuplicates($table, $config['parent']);

            Schema::table($table, function (Blueprint $blueprint) use ($config) {
                $blueprint->unique(['user_id', $config['parent']], $config['index']);
            });

            Log::info('UNA-004 membership consolidation', ['table' => $table] + $this->summary[$table]);
        }

        if (($this->summary['user_workspaces']['duplicate_groups'] ?? 0) > 0
            && class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES, true) as $table => $config) {
            if (!Schema::hasIndex($table, $config['index'])) {
                continue;
            }

            $this->keepForeignKeyIndex($table, $config['index']);

            Schema::table($table, function (Blueprint $blueprint) use ($config) {
                $blueprint->dropUnique($config['index']);
            });
        }
    }

    // ---------------------------------------------------------------- consolidation

    private function consolidate(string $table, string $parent): void
    {
        $batches = 0;
        while (true) {
            // No offset: every processed group stops being a duplicate, so the next query
            // returns the next unprocessed groups.
            $groups = DB::table($table)
                ->select('user_id', $parent)
                ->groupBy('user_id', $parent)
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('user_id')->orderBy($parent)
                ->limit(self::BATCH)
                ->get();

            if ($groups->isEmpty()) {
                return;
            }
            if (++$batches > 100000) {
                throw new RuntimeException("UNA-004: {$table} consolidation is not making progress.");
            }

            DB::transaction(function () use ($table, $parent, $groups) {
                foreach ($groups as $group) {
                    $this->mergeGroup($table, $parent, (int) $group->user_id, (int) $group->{$parent});
                }
            });
        }
    }

    private function mergeGroup(string $table, string $parent, int $userId, int $parentId): void
    {
        $rows = DB::table($table)->where('user_id', $userId)->where($parent, $parentId)
            ->orderBy('id')->lockForUpdate()->get();
        if ($rows->count() < 2) {
            return;
        }

        $resolution = match ($table) {
            'pod_users' => $this->resolvePodUsers($rows),
            'user_workspaces' => $this->resolveUserWorkspaces($rows),
            'user_projects' => $this->resolveUserProjects($rows),
        };
        $survivorId = $resolution['id'];
        $redundantIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $survivorId)->values()->all();

        DB::table($table)->where('id', $survivorId)->update($resolution['attributes']);

        if ($table === 'user_workspaces') {
            $this->moveSpatieAssignments($survivorId, $redundantIds);
        }

        $this->repointNotifications($table, $parent, $userId, $parentId, $survivorId, $resolution['attributes']['status'], $redundantIds);

        DB::table($table)->whereIn('id', $redundantIds)->delete();

        $this->summary[$table]['duplicate_groups']++;
        $this->summary[$table]['rows_removed'] += count($redundantIds);
    }

    // ---------------------------------------------------------------- merge policy

    /** @return array{id: int, attributes: array} */
    public function resolvePodUsers(Collection $rows): array
    {
        $this->assertUnambiguous('pod_users', $rows);

        $owners = $rows->where('permission', 'Owner');
        $active = $rows->where('status', 'active');

        if ($owners->isNotEmpty()) {
            $survivor = $owners->sortBy('id')->first();
            $status = $active->isNotEmpty() ? 'active' : $this->mostCurrent($owners)->status;
            $permission = 'Owner';
        } elseif ($active->isNotEmpty()) {
            $survivor = $active->sortBy('id')->first();
            $status = 'active';
            $permission = $survivor->permission;
        } else {
            $status = $this->mostCurrent($rows)->status;
            $survivor = $rows->where('status', $status)->sortBy('id')->first();
            $permission = $survivor->permission;
        }

        $reason = $survivor->reason;
        if ($status === 'declined') {
            $withReason = $rows->where('status', 'declined')->filter(fn ($row) => trim((string) $row->reason) !== '');
            $reason = $withReason->isNotEmpty() ? $this->mostCurrent($withReason)->reason : $survivor->reason;
        }

        return ['id' => (int) $survivor->id, 'attributes' => [
            'permission' => $permission,
            'status' => $status,
            'reason' => $reason,
        ] + $this->timestamps($rows)];
    }

    /** @return array{id: int, attributes: array} */
    public function resolveUserWorkspaces(Collection $rows): array
    {
        $this->assertUnambiguous('user_workspaces', $rows);

        $owners = $rows->where('permission', 'Owner');
        $active = $rows->where('status', 'active');
        $enabledActive = $active->filter(fn ($row) => (int) $row->is_active === 1);

        if ($owners->isNotEmpty()) {
            $survivor = $owners->sortBy('id')->first();
            $status = $active->isNotEmpty() ? 'active' : $this->mostCurrent($owners)->status;
            $permission = 'Owner';
        } elseif ($active->isNotEmpty()) {
            $survivor = ($enabledActive->isNotEmpty() ? $enabledActive : $active)->sortBy('id')->first();
            $status = 'active';
            $permission = $survivor->permission;
        } else {
            $status = $this->mostCurrent($rows)->status;
            $survivor = $rows->where('status', $status)->sortBy('id')->first();
            $permission = $survivor->permission;
        }

        $isActive = match (true) {
            $status === 'active' => $enabledActive->isNotEmpty() || ($active->isEmpty() && (int) $survivor->is_active === 1) ? 1 : 0,
            $status === 'declined' => 0,
            default => (int) $survivor->is_active,
        };

        return ['id' => (int) $survivor->id, 'attributes' => [
            'permission' => $permission,
            'status' => $status,
            'is_active' => $isActive,
        ] + $this->timestamps($rows)];
    }

    /** @return array{id: int, attributes: array} */
    public function resolveUserProjects(Collection $rows): array
    {
        $this->assertUnambiguous('user_projects', $rows);

        $active = $rows->where('status', 'active');
        $enabledActive = $active->filter(fn ($row) => (int) $row->is_active === 1);

        if ($active->isNotEmpty()) {
            $survivor = ($enabledActive->isNotEmpty() ? $enabledActive : $active)->sortBy('id')->first();
            $status = 'active';
            $isActive = $enabledActive->isNotEmpty() ? 1 : 0;
        } else {
            $status = $this->mostCurrent($rows)->status;
            $survivor = $rows->where('status', $status)->sortBy('id')->first();
            $isActive = 0; // a pending or declined claim is never an active membership
        }

        $permission = $survivor->permission;
        if ($permission === null) {
            $withPermission = $rows->whereNotNull('permission');
            $permission = $withPermission->isNotEmpty() ? $this->mostCurrent($withPermission)->permission : null;
        }

        return ['id' => (int) $survivor->id, 'attributes' => [
            'status' => $status,
            'is_active' => $isActive,
            'permission' => $permission,
        ] + $this->timestamps($rows)];
    }

    private function assertUnambiguous(string $table, Collection $rows): void
    {
        $statuses = $rows->pluck('status')->unique();
        $unknown = $statuses->diff(self::KNOWN_STATUSES[$table]);
        if ($unknown->isNotEmpty() && $statuses->count() > 1) {
            throw new RuntimeException("UNA-004: cannot consolidate {$table} rows [" . $rows->pluck('id')->implode(',') . '] with conflicting unknown statuses.');
        }

        if ($table !== 'user_projects') {
            $permissions = $rows->pluck('permission')->unique();
            $unknown = $permissions->diff(['Owner', 'Member']);
            if ($unknown->isNotEmpty() && $permissions->count() > 1 && !$permissions->contains('Owner')) {
                throw new RuntimeException("UNA-004: cannot consolidate {$table} rows [" . $rows->pluck('id')->implode(',') . '] with conflicting unknown permissions.');
            }
        }
    }

    /** Most recently updated row; null timestamps sort oldest, id breaks ties (higher = newer). */
    private function mostCurrent(Collection $rows): object
    {
        return $rows->sort(function ($a, $b) {
            return [(string) $b->updated_at, (int) $b->id] <=> [(string) $a->updated_at, (int) $a->id];
        })->first();
    }

    private function timestamps(Collection $rows): array
    {
        $created = $rows->pluck('created_at')->filter()->sort()->first();
        $updated = $rows->pluck('updated_at')->filter()->sort()->last();

        return ['created_at' => $created, 'updated_at' => $updated];
    }

    // ---------------------------------------------------------------- dependent references

    private function moveSpatieAssignments(int $survivorId, array $redundantIds): void
    {
        foreach (['model_has_permissions', 'model_has_roles'] as $pivot) {
            if (!Schema::hasTable($pivot)) {
                continue;
            }

            $assignments = DB::table($pivot)->where('model_type', self::WORKSPACE_MORPH)->whereIn('model_id', $redundantIds)->get();
            foreach ($assignments as $assignment) {
                DB::table($pivot)->insertOrIgnore(['model_id' => $survivorId] + (array) $assignment);
            }

            DB::table($pivot)->where('model_type', self::WORKSPACE_MORPH)->whereIn('model_id', $redundantIds)->delete();
        }
    }

    private function repointNotifications(string $table, string $parent, int $userId, int $parentId, int $survivorId, string $survivorStatus, array $redundantIds): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }
        $types = self::NOTIFICATION_TYPES[$table];

        foreach ($redundantIds as $redundantId) {
            $candidates = DB::table('notifications')
                ->whereIn('type', $types)
                ->where(fn ($q) => $q->where('related_id', $redundantId)->orWhere('data', 'like', '%"id":' . $redundantId . '%'))
                ->orderBy('id')
                ->get(['id', 'type', 'user_id', 'data']);

            foreach ($candidates as $notification) {
                $data = json_decode((string) $notification->data, true);
                // Only payloads that really are this membership row (same id, user and parent).
                if (!is_array($data)
                    || (int) ($data['id'] ?? 0) !== $redundantId
                    || (int) ($data['user_id'] ?? 0) !== $userId
                    || (int) ($data[$parent] ?? 0) !== $parentId) {
                    continue;
                }

                $obsolete = isset(self::ACTION_NOTIFICATIONS[$notification->type])
                    && self::ACTION_NOTIFICATIONS[$notification->type] !== $survivorStatus;
                $duplicate = DB::table('notifications')
                    ->where('type', $notification->type)
                    ->where('user_id', $notification->user_id)
                    ->where('related_id', $survivorId)
                    ->where('id', '!=', $notification->id)
                    ->exists();

                if ($obsolete || $duplicate) {
                    DB::table('notifications')->where('id', $notification->id)->delete();
                    $this->summary[$table]['notifications_removed']++;
                    continue;
                }

                $data['id'] = $survivorId;
                DB::table('notifications')->where('id', $notification->id)->update([
                    'data' => json_encode($data),
                    'related_id' => $survivorId,
                ]);
                $this->summary[$table]['notifications_repointed']++;
            }
        }
    }

    // ---------------------------------------------------------------- verification

    private function assertNoDuplicates(string $table, string $parent): void
    {
        $remaining = DB::table($table)->select('user_id', $parent)->groupBy('user_id', $parent)->havingRaw('COUNT(*) > 1')->limit(1)->count();

        if ($remaining > 0) {
            throw new RuntimeException("UNA-004: duplicate ({$parent}) memberships remain in {$table}; unique index not created.");
        }
    }

    /**
     * MySQL may silently drop the implicit index backing a user_id foreign key once the
     * composite unique index exists. Make sure a user_id index exists before dropping it.
     */
    private function keepForeignKeyIndex(string $table, string $uniqueIndex): void
    {
        $hasUserIdForeignKey = collect(Schema::getForeignKeys($table))->contains(fn ($fk) => ($fk['columns'] ?? []) === ['user_id']);
        if (!$hasUserIdForeignKey) {
            return;
        }

        $otherUserIdIndex = collect(Schema::getIndexes($table))
            ->contains(fn ($index) => $index['name'] !== $uniqueIndex && ($index['columns'][0] ?? null) === 'user_id');

        if (!$otherUserIdIndex) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index(['user_id'], "{$table}_user_id_index"));
        }
    }
};

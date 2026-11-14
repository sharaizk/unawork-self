<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNA-002: reconcile every User to the canonical account identity model.
 *
 *   users.type = 'admin' -> administrator, holds neither persona role
 *   users.type = 'user'  -> exactly one Spatie persona role: company XOR employee
 *
 * Clients (clients table / client guard) are never read or written.
 * Only the web-guard 'company' and 'employee' roles assigned to App\Models\User are
 * touched; every other role, guard and model type is left exactly as it is.
 *
 * Deterministic backfill matrix (first matching rule wins, see decide()):
 *   1. type === 'admin' (exact)            -> admin; strip company/employee roles
 *   2. holds company role (± employee)     -> company (explicit role; company wins a conflict)
 *   3. holds employee role                 -> employee
 *   4. legacy type 'company'               -> company
 *   5. legacy type 'employee'              -> employee
 *   6. owns a job vacancy                  -> company   (only companies post vacancies)
 *   7. has user_skills rows                -> employee  (hiring profile)
 *   8. owns a non-POD workspace and plan IS NOT NULL
 *                                          -> company   (admin "Create Company" / CSV import)
 *   9. anything else                       -> employee  (POD/workspace/project invitations)
 * Any non-'admin' type value is normalized to 'user'.
 *
 * Every change is recorded in una002_account_role_changes so down() restores the exact
 * previous state (types, removed roles, added roles, created role records) and nothing else.
 *
 * Self-contained on purpose: no application models or helpers, so future model changes
 * cannot alter what this migration does.
 */
return new class extends Migration
{
    private const LOG_TABLE = 'una002_account_role_changes';
    private const USER_MORPH = 'App\\Models\\User';
    private const POD_MORPH = 'App\\Models\\Pod';
    private const GUARD = 'web';
    private const ADMIN = 'admin';
    private const USER = 'user';
    private const COMPANY = 'company';
    private const EMPLOYEE = 'employee';
    private const CHUNK = 500;

    public function up(): void
    {
        if (!Schema::hasTable(self::LOG_TABLE)) {
            Schema::create(self::LOG_TABLE, function (Blueprint $table) {
                $table->id();
                $table->string('change', 20); // type | add_role | remove_role | create_role
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('previous_type', 20)->nullable();
                $table->string('reason', 64)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        DB::transaction(function () {
            $roleIds = $this->ensureAccountRoles();

            DB::table('users')->select(['id', 'type', 'plan'])
                ->chunkById(self::CHUNK, function (Collection $users) use ($roleIds) {
                    $this->reconcileChunk($users, $roleIds);
                });
        });

        $this->flushPermissionCache();
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::LOG_TABLE)) {
            return;
        }

        DB::transaction(function () {
            // Undo newest first so a role created by up() is checked only after its assignments are gone.
            DB::table(self::LOG_TABLE)->lazyByIdDesc(self::CHUNK)->each(function ($change) {
                match ($change->change) {
                    'type' => DB::table('users')->where('id', $change->user_id)->update(['type' => $change->previous_type]),
                    'add_role' => DB::table('model_has_roles')
                        ->where('model_type', self::USER_MORPH)
                        ->where('model_id', $change->user_id)
                        ->where('role_id', $change->role_id)
                        ->delete(),
                    'remove_role' => DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $change->role_id,
                        'model_type' => self::USER_MORPH,
                        'model_id' => $change->user_id,
                    ]),
                    'create_role' => $this->deleteRoleIfUnused((int) $change->role_id),
                    default => null,
                };
            });
        });

        Schema::dropIfExists(self::LOG_TABLE);
        $this->flushPermissionCache();
    }

    /**
     * Pure decision function for one user. Returns [targetType, targetRole|null, reason].
     * Public so the matrix can be tested directly.
     */
    public function decide(
        ?string $type,
        $plan,
        bool $hasCompanyRole,
        bool $hasEmployeeRole,
        bool $ownsJobVacancy,
        bool $hasSkills,
        bool $ownsCompanyWorkspace
    ): array {
        // Exact match only: the application has always compared users.type === 'admin'.
        if ($type === self::ADMIN) {
            return [self::ADMIN, null, 'administrator'];
        }

        $legacy = strtolower(trim((string) $type));

        return match (true) {
            $hasCompanyRole && $hasEmployeeRole => [self::USER, self::COMPANY, 'conflict:company-wins'],
            $hasCompanyRole => [self::USER, self::COMPANY, 'company-role'],
            $hasEmployeeRole => [self::USER, self::EMPLOYEE, 'employee-role'],
            $legacy === self::COMPANY => [self::USER, self::COMPANY, 'legacy-type'],
            $legacy === self::EMPLOYEE => [self::USER, self::EMPLOYEE, 'legacy-type'],
            $ownsJobVacancy => [self::USER, self::COMPANY, 'owns-job-vacancy'],
            $hasSkills => [self::USER, self::EMPLOYEE, 'has-skills'],
            $ownsCompanyWorkspace && $plan !== null => [self::USER, self::COMPANY, 'owns-workspace-with-plan'],
            default => [self::USER, self::EMPLOYEE, 'invited-or-generated'],
        };
    }

    /** @return array{company: int, employee: int} */
    private function ensureAccountRoles(): array
    {
        $ids = [];
        foreach ([self::COMPANY, self::EMPLOYEE] as $name) {
            $id = DB::table('roles')->where('name', $name)->where('guard_name', self::GUARD)->value('id');

            if (!$id) {
                $id = DB::table('roles')->insertGetId([
                    'name' => $name,
                    'guard_name' => self::GUARD,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->log('create_role', null, (int) $id, null, 'missing-role');
            }

            $ids[$name] = (int) $id;
        }

        return $ids;
    }

    private function reconcileChunk(Collection $users, array $roleIds): void
    {
        $userIds = $users->pluck('id')->all();

        $held = DB::table('model_has_roles')
            ->where('model_type', self::USER_MORPH)
            ->whereIn('model_id', $userIds)
            ->whereIn('role_id', array_values($roleIds))
            ->get(['model_id', 'role_id'])
            ->groupBy('model_id');

        $vacancyOwners = $this->idSet('job_vacancies', 'user_id', $userIds);
        $skilled = $this->idSet('user_skills', 'user_id', $userIds);
        $workspaceOwners = Schema::hasTable('workspaces')
            ? DB::table('workspaces')
                ->whereIn('created_by', $userIds)
                ->where(fn ($q) => $q->whereNull('ownerable_type')->orWhere('ownerable_type', '!=', self::POD_MORPH))
                ->distinct()->pluck('created_by')->map(fn ($id) => (int) $id)->flip()
            : collect();

        foreach ($users as $user) {
            $id = (int) $user->id;
            $roles = $held->get($user->id, collect())->pluck('role_id')->map(fn ($r) => (int) $r)->all();
            $hasCompany = in_array($roleIds[self::COMPANY], $roles, true);
            $hasEmployee = in_array($roleIds[self::EMPLOYEE], $roles, true);

            [$type, $role, $reason] = $this->decide(
                $user->type,
                $user->plan,
                $hasCompany,
                $hasEmployee,
                $vacancyOwners->has($id),
                $skilled->has($id),
                $workspaceOwners->has($id)
            );

            if ($user->type !== $type) {
                DB::table('users')->where('id', $id)->update(['type' => $type]);
                $this->log('type', $id, null, $user->type, $reason);
            }

            foreach ([self::COMPANY => $hasCompany, self::EMPLOYEE => $hasEmployee] as $name => $holds) {
                $roleId = $roleIds[$name];
                $wanted = $role === $name;

                if ($holds && !$wanted) {
                    DB::table('model_has_roles')
                        ->where('model_type', self::USER_MORPH)
                        ->where('model_id', $id)
                        ->where('role_id', $roleId)
                        ->delete();
                    $this->log('remove_role', $id, $roleId, null, $reason);
                } elseif (!$holds && $wanted) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $roleId,
                        'model_type' => self::USER_MORPH,
                        'model_id' => $id,
                    ]);
                    $this->log('add_role', $id, $roleId, null, $reason);
                }
            }
        }
    }

    private function idSet(string $table, string $column, array $userIds): Collection
    {
        if (!Schema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)->whereIn($column, $userIds)->distinct()->pluck($column)
            ->map(fn ($id) => (int) $id)->flip();
    }

    private function deleteRoleIfUnused(int $roleId): void
    {
        $used = DB::table('model_has_roles')->where('role_id', $roleId)->exists()
            || DB::table('role_has_permissions')->where('role_id', $roleId)->exists();

        if (!$used) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }

    private function log(string $change, ?int $userId, ?int $roleId, ?string $previousType, string $reason): void
    {
        DB::table(self::LOG_TABLE)->insert([
            'change' => $change,
            'user_id' => $userId,
            'role_id' => $roleId,
            'previous_type' => $previousType,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function flushPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};

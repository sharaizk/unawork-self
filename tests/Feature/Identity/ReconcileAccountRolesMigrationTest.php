<?php

namespace Tests\Feature\Identity;

use App\Enums\User\AccountType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-002: runs the real reconciliation migration (up/down) against legacy fixtures on
 * in-memory SQLite, plus its deterministic decision matrix.
 */
class ReconcileAccountRolesMigrationTest extends TestCase
{
    use UsesInMemorySqlite;

    private const MIGRATION = 'migrations/2026_11_14_100000_reconcile_user_account_roles.php';
    private const USER = 'App\\Models\\User';
    private const CLIENT = 'App\\Models\\Client';

    private object $migration;
    private array $ids = [];
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->migration = require database_path(self::MIGRATION);
    }

    // -- Decision matrix ------------------------------------------------------------------

    public static function matrix(): array
    {
        // type, plan, company role, employee role, vacancy, skills, owns company workspace => [type, role]
        return [
            'admin keeps type, loses persona' => ['admin', 1, true, true, true, true, true, ['admin', null]],
            'admin-like casing is NOT admin'   => ['Admin', 1, false, false, false, false, false, ['user', 'employee']],
            'company role'                     => ['user', 1, true, false, false, false, false, ['user', 'company']],
            'employee role'                    => ['user', 1, false, true, false, false, false, ['user', 'employee']],
            'both roles: company wins'         => ['user', 1, true, true, false, true, false, ['user', 'company']],
            'role beats legacy type'           => ['company', null, false, true, false, false, false, ['user', 'employee']],
            'legacy type company'              => ['company', null, false, false, false, true, false, ['user', 'company']],
            'legacy type employee'             => [' Employee ', 1, false, false, true, false, true, ['user', 'employee']],
            'role-less vacancy owner'          => ['user', null, false, false, true, true, false, ['user', 'company']],
            'role-less with skills'            => ['user', 1, false, false, false, true, true, ['user', 'employee']],
            'role-less company workspace+plan' => ['user', 1, false, false, false, false, true, ['user', 'company']],
            'role-less workspace, no plan'     => ['user', null, false, false, false, false, true, ['user', 'employee']],
            'role-less invited account'        => ['user', null, false, false, false, false, false, ['user', 'employee']],
            'unknown type normalized'          => ['super admin', 1, false, false, false, false, false, ['user', 'employee']],
        ];
    }

    #[DataProvider('matrix')]
    public function test_decision_matrix(?string $type, $plan, bool $company, bool $employee, bool $vacancy, bool $skills, bool $workspace, array $expected): void
    {
        [$targetType, $targetRole] = $this->migration->decide($type, $plan, $company, $employee, $vacancy, $skills, $workspace);

        $this->assertSame($expected, [$targetType, $targetRole]);
    }

    // -- Full migration ---------------------------------------------------------------------

    public function test_up_reconciles_legacy_users_and_leaves_everything_else_alone(): void
    {
        $this->seedLegacyFixture();
        $clientsBefore = DB::table('clients')->get()->toArray();
        $foreignRolesBefore = $this->foreignRoleRows();

        $this->migration->up();

        $expect = [
            'admin' => ['admin', []],
            'company' => ['user', ['company']],
            'employee' => ['user', ['employee']],
            'dual' => ['user', ['company']],
            'legacyCompany' => ['user', ['company']],
            'legacyEmployee' => ['user', ['employee']],
            'legacyConflict' => ['user', ['employee']],
            'vacancyOwner' => ['user', ['company']],
            'skilled' => ['user', ['employee']],
            'adminCreatedCompany' => ['user', ['company']],
            'podOwnerOnly' => ['user', ['employee']],
            'invited' => ['user', ['employee']],
            'moderatorEmployee' => ['user', ['employee', 'moderator']],
            'apiGuardCompany' => ['user', ['employee']],
        ];
        foreach ($expect as $key => [$type, $roles]) {
            $this->assertSame($type, DB::table('users')->where('id', $this->ids[$key])->value('type'), "{$key} type");
            $this->assertEqualsCanonicalizing($roles, $this->webRoleNames($this->ids[$key]), "{$key} roles");
        }

        // The api-guard 'company' role assignment is untouched.
        $this->assertTrue(DB::table('model_has_roles')->where('model_id', $this->ids['apiGuardCompany'])->where('role_id', $this->roles['company_api'])->exists());

        // Clients and non-User role rows are untouched; no client gained a role.
        $this->assertEquals($clientsBefore, DB::table('clients')->get()->toArray());
        $this->assertEquals($foreignRolesBefore, $this->foreignRoleRows());

        // The canonical API agrees with the stored state: every non-admin is exactly one persona.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (User::all() as $user) {
            $types = array_filter([$user->isAdministrator(), $user->isCompany(), $user->isEmployee()]);
            $this->assertCount(1, $types, "user {$user->email} resolves to exactly one account type");
        }
        $this->assertSame(AccountType::COMPANY, User::find($this->ids['dual'])->accountType());
        $this->assertSame(0, User::companies()->whereIn('id', User::employees()->pluck('id'))->count());
    }

    public function test_up_is_idempotent(): void
    {
        $this->seedLegacyFixture();

        $this->migration->up();
        $state = $this->snapshot();
        $changes = DB::table('una002_account_role_changes')->count();

        $this->migration->up();

        $this->assertEquals($state, $this->snapshot());
        $this->assertSame($changes, DB::table('una002_account_role_changes')->count());
    }

    public function test_down_restores_exactly_the_previous_state(): void
    {
        $this->seedLegacyFixture();
        $before = $this->snapshot();

        $this->migration->up();
        $this->assertNotEquals($before, $this->snapshot());

        $this->migration->down();

        $this->assertEquals($before, $this->snapshot());
        $this->assertFalse(Schema::hasTable('una002_account_role_changes'));
    }

    public function test_up_processes_large_tables_in_chunks_and_creates_missing_roles(): void
    {
        $this->seedRoles();
        $rows = [];
        for ($i = 0; $i < 1205; $i++) {
            $rows[] = ['name' => "Bulk {$i}", 'email' => "bulk{$i}@example.test", 'type' => $i % 2 ? 'company' : 'user', 'plan' => null];
        }
        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $this->migration->up();

        $this->assertSame(0, DB::table('users')->where('type', '!=', 'user')->count());
        $roleCount = DB::table('model_has_roles')->where('model_type', self::USER)->count();
        $this->assertSame(1205, $roleCount, 'every bulk user has exactly one persona role');
        // Odd indices (602) were legacy type 'company'; even ones (603) are role-less invited accounts.
        $this->assertSame(602, DB::table('model_has_roles')->where('role_id', $this->roles['company'])->count());

        // The missing web 'employee' role was created (and only that one)...
        $employeeRole = DB::table('roles')->where('name', 'employee')->where('guard_name', 'web')->value('id');
        $this->assertNotNull($employeeRole);
        $this->assertSame(603, DB::table('model_has_roles')->where('role_id', $employeeRole)->count());
        $this->assertSame(4, DB::table('roles')->count());

        // ...and rolling back removes it again together with the assignments it carried.
        $this->migration->down();
        $this->assertFalse(DB::table('roles')->where('id', $employeeRole)->exists());
        $this->assertSame(0, DB::table('model_has_roles')->count());
        $this->assertSame(603, DB::table('users')->where('type', 'user')->count());
        $this->assertSame(602, DB::table('users')->where('type', 'company')->count());
    }

    // -- Fixture ------------------------------------------------------------------------------

    private function seedRoles(): void
    {
        $this->roles['company'] = DB::table('roles')->insertGetId(['name' => 'company', 'guard_name' => 'web']);
        $this->roles['company_api'] = DB::table('roles')->insertGetId(['name' => 'company', 'guard_name' => 'api']);
        $this->roles['moderator'] = DB::table('roles')->insertGetId(['name' => 'moderator', 'guard_name' => 'web']);
        // 'employee' (web) is deliberately missing: the migration must create it.
    }

    private function seedLegacyFixture(): void
    {
        $this->seedRoles();
        $employeeRole = DB::table('roles')->insertGetId(['name' => 'employee', 'guard_name' => 'legacy']);

        $users = [
            'admin' => ['type' => 'admin', 'plan' => null],
            'company' => ['type' => 'user', 'plan' => 1],
            'employee' => ['type' => 'user', 'plan' => 1],
            'dual' => ['type' => 'user', 'plan' => 1],
            'legacyCompany' => ['type' => 'company', 'plan' => null],
            'legacyEmployee' => ['type' => 'employee', 'plan' => 1],
            'legacyConflict' => ['type' => 'Company', 'plan' => 1],
            'vacancyOwner' => ['type' => 'user', 'plan' => null],
            'skilled' => ['type' => 'user', 'plan' => 1],
            'adminCreatedCompany' => ['type' => 'user', 'plan' => 1],
            'podOwnerOnly' => ['type' => 'user', 'plan' => 1],
            'invited' => ['type' => 'user', 'plan' => null],
            'moderatorEmployee' => ['type' => 'user', 'plan' => 1],
            'apiGuardCompany' => ['type' => 'user', 'plan' => null],
        ];
        foreach ($users as $key => $attributes) {
            $this->ids[$key] = DB::table('users')->insertGetId($attributes + ['name' => $key, 'email' => "{$key}@example.test"]);
        }

        // This fixture needs existing employee holders, so it creates the web 'employee' role
        // (the chunk test covers the migration creating a missing one).
        $webEmployee = DB::table('roles')->insertGetId(['name' => 'employee', 'guard_name' => 'web']);
        $assign = fn (string $key, int $roleId) => DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => self::USER, 'model_id' => $this->ids[$key]]);
        $assign('admin', $this->roles['company']);
        $assign('company', $this->roles['company']);
        $assign('employee', $webEmployee);
        $assign('dual', $this->roles['company']);
        $assign('dual', $webEmployee);
        $assign('legacyConflict', $webEmployee);
        $assign('moderatorEmployee', $webEmployee);
        $assign('moderatorEmployee', $this->roles['moderator']);
        $assign('apiGuardCompany', $this->roles['company_api']);
        $assign('apiGuardCompany', $employeeRole); // different guard: not an account role

        DB::table('job_vacancies')->insert(['user_id' => $this->ids['vacancyOwner'], 'title' => 'Job']);
        DB::table('user_skills')->insert(['user_id' => $this->ids['skilled'], 'skill_id' => 1]);
        DB::table('workspaces')->insert([
            ['name' => 'Acme', 'created_by' => $this->ids['adminCreatedCompany'], 'ownerable_type' => self::USER, 'ownerable_id' => $this->ids['adminCreatedCompany']],
            ['name' => 'Pod WS', 'created_by' => $this->ids['podOwnerOnly'], 'ownerable_type' => 'App\\Models\\Pod', 'ownerable_id' => 1],
        ]);

        // Clients, including a (wrong) role row pointing at a client: both must stay untouched.
        $clientId = DB::table('clients')->insertGetId(['name' => 'Client', 'email' => 'client@example.test']);
        DB::table('model_has_roles')->insert(['role_id' => $this->roles['company'], 'model_type' => self::CLIENT, 'model_id' => $clientId]);
    }

    private function webRoleNames(int $userId): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', self::USER)->where('model_id', $userId)->where('roles.guard_name', 'web')
            ->pluck('roles.name')->all();
    }

    private function foreignRoleRows(): array
    {
        return DB::table('model_has_roles')->where('model_type', '!=', self::USER)
            ->orWhereIn('role_id', DB::table('roles')->where('guard_name', '!=', 'web')->pluck('id'))
            ->orderBy('role_id')->orderBy('model_id')->get()->toArray();
    }

    private function snapshot(): array
    {
        return [
            'users' => DB::table('users')->orderBy('id')->get(['id', 'type'])->toArray(),
            'model_has_roles' => DB::table('model_has_roles')->orderBy('model_type')->orderBy('model_id')->orderBy('role_id')->get()->toArray(),
            'roles' => DB::table('roles')->orderBy('id')->get(['id', 'name', 'guard_name'])->toArray(),
            'clients' => DB::table('clients')->orderBy('id')->get()->toArray(),
        ];
    }
}

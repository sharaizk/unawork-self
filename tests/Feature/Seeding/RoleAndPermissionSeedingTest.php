<?php

namespace Tests\Feature\Seeding;

use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Models\Client;
use App\Models\User;
use App\Models\UserWorkspace;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-003: account roles and workspace permissions are seeded from their enums, idempotently,
 * and a freshly seeded installation supports registration and workspace permission grants.
 * Runs only on in-memory SQLite (see UsesInMemorySqlite).
 */
class RoleAndPermissionSeedingTest extends TestCase
{
    use UsesInMemorySqlite;

    private const FOUNDATION = [RolesAndPermissionsSeeder::class, PermissionsSeeder::class];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
    }

    // -- 1-2. Fresh database --------------------------------------------------------------

    public function test_role_seeder_creates_every_user_role_under_the_web_guard(): void
    {
        $this->runSeeders([RolesAndPermissionsSeeder::class]);

        $this->assertEqualsCanonicalizing($this->roleValues(), $this->names('roles', 'web'));
        $this->assertSame(count(UserRole::cases()), DB::table('roles')->count());
        $this->assertSame(0, DB::table('permissions')->count(), 'roles seeder must not create workspace permissions');
        $this->assertSame(0, DB::table('role_has_permissions')->count(), 'account roles carry no workspace permissions');
    }

    public function test_permission_seeder_creates_every_workspace_permission_under_the_web_guard(): void
    {
        $this->runSeeders([PermissionsSeeder::class]);

        $this->assertEqualsCanonicalizing(WorkspacePermission::values(), $this->names('permissions', 'web'));
        $this->assertSame(count(WorkspacePermission::cases()), DB::table('permissions')->count());
        $this->assertSame(0, DB::table('roles')->count());
    }

    // -- 3-5. Reruns ------------------------------------------------------------------------

    public function test_seeders_can_run_repeatedly_without_changing_counts_or_ids(): void
    {
        $this->runSeeders(self::FOUNDATION);
        $roles = $this->rows('roles');
        $permissions = $this->rows('permissions');

        $this->runSeeders(self::FOUNDATION);
        $this->runSeeders(array_reverse(self::FOUNDATION));

        $this->assertEquals($roles, $this->rows('roles'));
        $this->assertEquals($permissions, $this->rows('permissions'));
    }

    // -- 6-8. Partial, unrelated and other-guard records -------------------------------------

    public function test_partial_installation_receives_only_missing_records_and_others_are_untouched(): void
    {
        $now = now();
        $employeeId = DB::table('roles')->insertGetId(['name' => 'employee', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        $moderatorId = DB::table('roles')->insertGetId(['name' => 'moderator', 'guard_name' => 'web']);
        $apiCompanyId = DB::table('roles')->insertGetId(['name' => 'company', 'guard_name' => 'api']);
        $newsEditId = DB::table('permissions')->insertGetId(['name' => 'news_edit', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        $billingId = DB::table('permissions')->insertGetId(['name' => 'billing', 'guard_name' => 'web']);
        $apiUsersEditId = DB::table('permissions')->insertGetId(['name' => 'users_edit', 'guard_name' => 'api']);
        $untouched = ['roles' => $this->rows('roles'), 'permissions' => $this->rows('permissions')];

        $this->runSeeders(self::FOUNDATION);

        // Pre-existing rows (required, unrelated and other-guard) are byte-for-byte unchanged.
        foreach ($untouched as $table => $rows) {
            foreach ($rows as $row) {
                $this->assertEquals($row, DB::table($table)->where('id', $row->id)->first(), "{$table} #{$row->id} changed");
            }
        }
        $this->assertSame($employeeId, (int) DB::table('roles')->where('name', 'employee')->where('guard_name', 'web')->value('id'));
        $this->assertSame($newsEditId, (int) DB::table('permissions')->where('name', 'news_edit')->where('guard_name', 'web')->value('id'));

        // Only the missing web records were added, despite same-named records on another guard.
        $this->assertEqualsCanonicalizing([...$this->roleValues(), 'moderator'], $this->names('roles', 'web'));
        $this->assertEqualsCanonicalizing([...WorkspacePermission::values(), 'billing'], $this->names('permissions', 'web'));
        $this->assertSame(['company'], $this->names('roles', 'api'));
        $this->assertSame(['users_edit'], $this->names('permissions', 'api'));
        $this->assertSame(count(UserRole::cases()) + 2, DB::table('roles')->count());
        $this->assertSame(count(WorkspacePermission::cases()) + 2, DB::table('permissions')->count());
        $this->assertNotNull(DB::table('roles')->find($moderatorId));
        $this->assertNotNull(DB::table('roles')->find($apiCompanyId));
        $this->assertNotNull(DB::table('permissions')->find($billingId));
        $this->assertNotNull(DB::table('permissions')->find($apiUsersEditId));
    }

    // -- 9-10. Existing assignments survive --------------------------------------------------

    public function test_existing_role_and_permission_assignments_survive_reruns(): void
    {
        $this->runSeeders(self::FOUNDATION);
        $company = User::createWithAccountRole(['name' => 'Co', 'email' => 'co@example.test'], UserRole::COMPANY);
        $userWorkspace = $this->ownerWorkspace($company);
        $userWorkspace->assignAllPermissionsToCreator();
        $roleAssignments = $this->rows('model_has_roles', 'model_id');
        $permissionAssignments = $this->rows('model_has_permissions', 'model_id');

        $this->runSeeders(self::FOUNDATION);
        $this->runSeeders(self::FOUNDATION);

        $this->assertEquals($roleAssignments, $this->rows('model_has_roles', 'model_id'));
        $this->assertEquals($permissionAssignments, $this->rows('model_has_permissions', 'model_id'));
        $this->assertTrue($company->fresh()->isCompany());
        $this->assertTrue($userWorkspace->fresh()->hasPermissionTo(WorkspacePermission::USERS_EDIT->value));
    }

    // -- 11. Cache -------------------------------------------------------------------------------

    public function test_newly_seeded_records_are_immediately_usable_despite_a_primed_cache(): void
    {
        // Prime Spatie's cache while nothing exists yet.
        $this->assertCount(0, app(PermissionRegistrar::class)->getPermissions());

        $this->runSeeders(self::FOUNDATION);

        $owner = User::createWithAccountRole(['name' => 'Owner', 'email' => 'owner@example.test'], UserRole::COMPANY);
        $userWorkspace = $this->ownerWorkspace($owner);
        $userWorkspace->givePermissionTo(WorkspacePermission::PROJECTS_EDIT->value); // throws PermissionDoesNotExist on a stale cache

        $this->assertTrue($userWorkspace->fresh()->hasPermissionTo(WorkspacePermission::PROJECTS_EDIT->value));
        $this->assertCount(count(WorkspacePermission::cases()), app(PermissionRegistrar::class)->getPermissions());
    }

    // -- 12. DatabaseSeeder -------------------------------------------------------------------

    public function test_database_seeder_seeds_roles_and_permissions_first_and_can_rerun(): void
    {
        $this->assertSame(self::FOUNDATION, DatabaseSeeder::FOUNDATION_SEEDERS, 'roles, then permissions');
        $this->createDatabaseSeederSchema();

        // The module commands are external state: stub them, and check they run after the foundation.
        Artisan::shouldReceive('call')->with('module:migrate LandingPage')->twice()->andReturnUsing(function () {
            $this->assertEqualsCanonicalizing($this->roleValues(), $this->names('roles', 'web'));
            $this->assertEqualsCanonicalizing(WorkspacePermission::values(), $this->names('permissions', 'web'));

            return 0;
        });
        Artisan::shouldReceive('call')->with('module:seed LandingPage')->twice()->andReturn(0);

        $this->runSeeders([DatabaseSeeder::class]);
        $roles = $this->rows('roles');
        $permissions = $this->rows('permissions');
        $this->runSeeders([DatabaseSeeder::class]);

        $this->assertEquals($roles, $this->rows('roles'));
        $this->assertEquals($permissions, $this->rows('permissions'));
    }

    // -- 13. Account roles after seeding -------------------------------------------------------

    public function test_company_and_employee_accounts_use_the_seeded_web_roles_without_duplicates(): void
    {
        $this->runSeeders(self::FOUNDATION);
        $seededRoles = $this->rows('roles');

        $company = User::createWithAccountRole(['name' => 'Co', 'email' => 'co@example.test'], UserRole::COMPANY);
        $employee = User::createWithAccountRole(['name' => 'Em', 'email' => 'em@example.test'], UserRole::EMPLOYEE);
        $employee->assignAccountRole(UserRole::EMPLOYEE); // repeated assignment

        $this->assertEquals($seededRoles, $this->rows('roles'), 'no role was created on demand');
        $this->assertTrue($company->fresh()->isCompany());
        $this->assertTrue($employee->fresh()->isEmployee());
        foreach ([$company, $employee] as $user) {
            $this->assertSame(1, DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $user->id)->count());
            $this->assertSame(['web'], $user->fresh()->roles->pluck('guard_name')->unique()->values()->all());
        }
    }

    // -- 14-15. Workspace permissions after seeding -------------------------------------------

    public function test_workspace_owner_receives_exactly_the_workspace_permissions(): void
    {
        DB::table('permissions')->insert([
            ['name' => 'billing', 'guard_name' => 'web'],          // unrelated web permission
            ['name' => 'users_edit', 'guard_name' => 'api'],       // same name, other guard
        ]);
        $this->runSeeders(self::FOUNDATION);

        $owner = User::createWithAccountRole(['name' => 'Owner', 'email' => 'owner@example.test'], UserRole::COMPANY);
        $userWorkspace = $this->ownerWorkspace($owner);
        $userWorkspace->assignAllPermissionsToCreator();

        $granted = $userWorkspace->fresh()->permissions;
        $this->assertEqualsCanonicalizing(WorkspacePermission::values(), $granted->pluck('name')->all());
        $this->assertSame(['web'], $granted->pluck('guard_name')->unique()->values()->all());
        $this->assertSame(0, DB::table('role_has_permissions')->count(), 'nothing granted to account roles');
    }

    public function test_workspace_member_receives_a_selected_subset(): void
    {
        $this->runSeeders(self::FOUNDATION);
        $owner = User::createWithAccountRole(['name' => 'Owner', 'email' => 'owner@example.test'], UserRole::COMPANY);
        $member = User::createWithAccountRole(['name' => 'Member', 'email' => 'member@example.test'], UserRole::EMPLOYEE);
        $workspaceId = $this->ownerWorkspace($owner)->workspace_id;
        $membership = UserWorkspace::create(['user_id' => $member->id, 'workspace_id' => $workspaceId, 'permission' => 'Member']);

        $subset = [WorkspacePermission::USERS_INDEX->value, WorkspacePermission::PROJECTS_INDEX->value];
        $membership->givePermissionTo($subset);

        $this->assertEqualsCanonicalizing($subset, $membership->fresh()->getPermissionNames()->all());
        $this->assertFalse($membership->fresh()->hasPermissionTo(WorkspacePermission::USERS_EDIT->value));
    }

    // -- 16. Clients ------------------------------------------------------------------------------

    public function test_clients_receive_no_roles_or_permissions(): void
    {
        Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test']);
        $this->createDatabaseSeederSchema();
        Artisan::shouldReceive('call')->andReturn(0);

        $this->runSeeders([DatabaseSeeder::class]);
        $owner = User::createWithAccountRole(['name' => 'Owner', 'email' => 'owner@example.test'], UserRole::COMPANY);
        $this->ownerWorkspace($owner)->assignAllPermissionsToCreator();

        $this->assertSame(0, DB::table('model_has_roles')->where('model_type', Client::class)->count());
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_type', Client::class)->count());
    }

    // -- Helpers ------------------------------------------------------------------------------------

    private function roleValues(): array
    {
        return array_map(fn (UserRole $role) => $role->value, UserRole::cases());
    }

    private function names(string $table, string $guard): array
    {
        return DB::table($table)->where('guard_name', $guard)->orderBy('name')->pluck('name')->all();
    }

    private function rows(string $table, string $orderBy = 'id'): array
    {
        return DB::table($table)->orderBy($orderBy)->get()->toArray();
    }

    private function ownerWorkspace(User $owner): UserWorkspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId(['name' => 'WS', 'slug' => 'ws-' . $owner->id, 'created_by' => $owner->id]);

        return UserWorkspace::create(['user_id' => $owner->id, 'workspace_id' => $workspaceId, 'permission' => 'Owner']);
    }

    /** Tables NotificationSeeder and the CLI branch (User::seed_languages) write to. */
    private function createDatabaseSeederSchema(): void
    {
        Schema::create('notification_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug');
            $t->timestamps();
        });
        Schema::create('notification_template_langs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('parent_id');
            $t->string('lang');
            $t->text('variables')->nullable();
            $t->text('content')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }
}

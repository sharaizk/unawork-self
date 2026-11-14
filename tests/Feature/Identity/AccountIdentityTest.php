<?php

namespace Tests\Feature\Identity;

use App\Enums\User\AccountType;
use App\Enums\User\UserRole;
use App\Enums\User\UserType;
use App\Models\Client;
use App\Models\Pod;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PodUserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-002: canonical account identity (administrator / company / employee / client),
 * its query scopes, the single write path, and every live account-creation path.
 * Runs only on in-memory SQLite (see UsesInMemorySqlite).
 */
class AccountIdentityTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();

        // Production seeders (UNA-003): registration and invitations run against exactly what a
        // fresh installation seeds.
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);

        Mail::fake();
        Bus::fake();
    }

    // -- 1-4. Resolution -----------------------------------------------------------

    public function test_administrator_resolves_only_as_administrator(): void
    {
        $admin = User::factory()->administrator()->create();
        // Even a stray persona role does not make an administrator a company/employee.
        $admin->assignRole(UserRole::COMPANY->value);

        $this->assertSame(AccountType::ADMINISTRATOR, $admin->fresh()->accountType());
        $this->assertTrue($admin->isAdministrator());
        $this->assertFalse($admin->fresh()->isCompany());
        $this->assertFalse($admin->isEmployee());
        $this->assertFalse($admin->isClient());
        $this->assertFalse($admin->isRegularUser());
    }

    public function test_company_resolves_only_as_company(): void
    {
        $company = User::factory()->company()->create();

        $this->assertSame(AccountType::COMPANY, $company->accountType());
        $this->assertTrue($company->isCompany());
        $this->assertTrue($company->isRegularUser());
        $this->assertFalse($company->isEmployee());
        $this->assertFalse($company->isAdministrator());
        $this->assertFalse($company->isClient());
    }

    public function test_employee_resolves_only_as_employee(): void
    {
        $employee = User::factory()->employee()->create();

        $this->assertSame(AccountType::EMPLOYEE, $employee->accountType());
        $this->assertTrue($employee->isEmployee());
        $this->assertFalse($employee->isCompany());
        $this->assertFalse($employee->isAdministrator());
        $this->assertFalse($employee->isClient());
    }

    public function test_client_resolves_only_as_client_even_inside_a_company_workspace(): void
    {
        $company = User::factory()->company()->create();
        $workspace = $this->ownWorkspace($company);
        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'x']);
        DB::table('client_workspaces')->insert(['client_id' => $client->id, 'workspace_id' => $workspace->id]);

        $this->assertSame(AccountType::CLIENT, $client->accountType());
        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isCompany());
        $this->assertFalse($client->isEmployee());
        $this->assertFalse($client->isAdministrator());
        $this->assertFalse($client->isRegularUser());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_type', Client::class)->count());
    }

    public function test_conflicting_roles_resolve_as_company_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole([UserRole::COMPANY->value, UserRole::EMPLOYEE->value]);

        $this->assertSame(AccountType::COMPANY, $user->fresh()->accountType());
        $this->assertFalse($user->fresh()->isEmployee());
        $this->assertTrue(User::companies()->whereKey($user->id)->exists());
        $this->assertFalse(User::employees()->whereKey($user->id)->exists());
    }

    // -- 5-6. Query scopes ------------------------------------------------------------

    public function test_company_and_employee_scopes_are_exclusive(): void
    {
        $admin = User::factory()->administrator()->create();
        $admin->assignRole(UserRole::EMPLOYEE->value); // stray role must not leak into scopes
        $company = User::factory()->company()->create();
        $employee = User::factory()->employee()->create();
        $unclassified = User::factory()->create();

        $this->assertSame([$company->id], User::companies()->pluck('id')->all());
        $this->assertSame([$employee->id], User::employees()->pluck('id')->all());
        $this->assertSame([$admin->id], User::administrators()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$company->id, $employee->id, $unclassified->id], User::nonAdministrators()->pluck('id')->all());
    }

    // -- 7-9. Web registration --------------------------------------------------------

    public function test_web_registration_creates_exactly_classified_accounts(): void
    {
        foreach ([UserRole::COMPANY, UserRole::EMPLOYEE] as $role) {
            $email = $role->value . '@example.test';
            $this->post('/register', $this->registration($email, $role->value))->assertRedirect();
            auth()->logout();

            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame(UserType::USER->value, $user->type);
            $this->assertSame([$role->value], $user->getRoleNames()->all());
            $this->assertNotNull($user->currant_workspace);
        }
    }

    public function test_web_registration_rejects_manipulated_roles(): void
    {
        foreach (['admin', 'client', 'Company ', '', 'company,employee'] as $role) {
            $email = 'bad-' . md5($role) . '@example.test';
            $this->post('/register', $this->registration($email, $role))->assertSessionHasErrors('role');
            $this->assertFalse(User::where('email', $email)->exists(), "role [{$role}] created a user");
        }

        $payload = $this->registration('missing@example.test', 'company');
        unset($payload['role']);
        $this->post('/register', $payload)->assertSessionHasErrors('role');
        $this->assertFalse(User::where('email', 'missing@example.test')->exists());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    // -- 10. API registration ---------------------------------------------------------

    public function test_api_registration_creates_an_employee(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Api Person', 'email' => 'api@example.test',
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        ])->assertOk();

        $user = User::where('email', 'api@example.test')->firstOrFail();
        $this->assertSame(UserType::USER->value, $user->type);
        $this->assertSame([UserRole::EMPLOYEE->value], $user->getRoleNames()->all());
    }

    // -- 11. Invitations ---------------------------------------------------------------

    public function test_pod_invitation_generates_an_employee(): void
    {
        $owner = User::factory()->company()->create();
        $pod = app(\App\Services\PodService::class)->createPod(['title' => 'Pod', 'created_by' => $owner->id]); // a POD always owns a workspace

        app(PodUserService::class)->inviteNewUser(
            ['name' => 'Invited', 'email' => 'pod-invite@example.test', 'password' => 'secret123'],
            $pod->id,
            []
        );

        $this->assertEmployee(User::where('email', 'pod-invite@example.test')->firstOrFail());
    }

    public function test_workspace_invitation_generates_an_employee(): void
    {
        $owner = User::factory()->company()->create(['plan' => 1]);
        $workspace = $this->ownWorkspace($owner);

        $this->actingAs($owner)->post(route('users.invite.update', $workspace->slug), [
            'username' => 'Invited', 'useremail' => 'ws-invite@example.test',
            'userpassword' => 'secret123', 'password_switch' => 'on',
        ])->assertOk();

        $this->assertEmployee(User::where('email', 'ws-invite@example.test')->firstOrFail());
    }

    // -- 12-13. Company / administrator creation ---------------------------------------

    public function test_administrator_create_company_creates_a_company(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Acme', 'workspace' => 'Acme WS', 'email' => 'acme@example.test',
            'password_switch' => 'on', 'password' => 'secret123',
        ])->assertRedirect();

        $company = User::where('email', 'acme@example.test')->firstOrFail();
        $this->assertSame(UserType::USER->value, $company->type);
        $this->assertSame([UserRole::COMPANY->value], $company->getRoleNames()->all());
        $this->assertTrue($admin->fresh()->isAdministrator());
        $this->assertSame([], $admin->fresh()->getRoleNames()->all());
    }

    public function test_admin_seeder_creates_an_unclassified_administrator(): void
    {
        $this->seed(\Database\Seeders\UsersTableSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame(AccountType::ADMINISTRATOR, $admin->accountType());
        $this->assertSame([], $admin->getRoleNames()->all());
    }

    public function test_administrators_cannot_receive_an_account_role(): void
    {
        $this->expectException(\LogicException::class);
        User::factory()->administrator()->create()->assignAccountRole(UserRole::COMPANY);
    }

    // -- 14-15. Write path ----------------------------------------------------------------

    public function test_changing_account_role_swaps_only_the_persona_role(): void
    {
        Role::create(['name' => 'moderator', 'guard_name' => 'web']);
        $user = User::factory()->employee()->create();
        $user->assignRole('moderator');

        $user->assignAccountRole(UserRole::COMPANY);

        $this->assertEqualsCanonicalizing(['company', 'moderator'], $user->fresh()->getRoleNames()->all());
        $this->assertSame(AccountType::COMPANY, $user->fresh()->accountType());

        // Idempotent and still exclusive.
        $user->fresh()->assignAccountRole(UserRole::COMPANY);
        $this->assertEqualsCanonicalizing(['company', 'moderator'], $user->fresh()->getRoleNames()->all());
    }

    public function test_assigning_a_role_normalizes_legacy_type(): void
    {
        $user = User::factory()->create(['type' => 'employee']);

        $user->assignAccountRole(UserRole::EMPLOYEE);

        $this->assertSame(UserType::USER->value, $user->fresh()->type);
        $this->assertSame([UserRole::EMPLOYEE->value], $user->fresh()->getRoleNames()->all());
    }

    // -- Imports -------------------------------------------------------------------------

    public function test_company_import_is_admin_only_and_normalizes_account_types(): void
    {
        $admin = User::factory()->administrator()->create();
        $company = User::factory()->company()->create();

        $csv = "name,email,password,type\n"
            . "A,import-a@example.test,secret,company\n"
            . "B,import-b@example.test,secret,Employee\n"
            . "C,import-c@example.test,secret,user\n";

        $this->actingAs($company)->post(route('user.import'), ['file' => $this->csv($csv)])->assertForbidden();
        $this->assertFalse(User::where('email', 'import-a@example.test')->exists());

        $this->actingAs($admin)->post(route('user.import'), ['file' => $this->csv($csv)])->assertSessionHas('success');

        $this->assertSame([UserRole::COMPANY->value], User::where('email', 'import-a@example.test')->first()->getRoleNames()->all());
        $this->assertEmployee(User::where('email', 'import-b@example.test')->first());
        $this->assertSame([UserRole::COMPANY->value], User::where('email', 'import-c@example.test')->first()->getRoleNames()->all());
        $this->assertSame(0, User::where('email', 'like', 'import-%')->where('type', '!=', 'user')->count());
    }

    public function test_company_import_rejects_invalid_types_and_administrators_without_writing(): void
    {
        $admin = User::factory()->administrator()->create();

        foreach (['admin', 'superuser', 'company;employee'] as $type) {
            $csv = "name,email,password,type\nOk,ok-{$type}@example.test,secret,company\nX,x@example.test,secret,{$type}\n";
            $this->actingAs($admin)->post(route('user.import'), ['file' => $this->csv($csv)])->assertSessionHas('error');
            $this->assertFalse(User::where('email', "ok-{$type}@example.test")->exists(), 'valid rows must not be written when a row is invalid');
        }

        $csv = "name,email,password,type\nTaken,{$admin->email},secret,company\n";
        $this->actingAs($admin)->post(route('user.import'), ['file' => $this->csv($csv)])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->isAdministrator());
    }

    // -- Helpers -------------------------------------------------------------------------

    private function registration(string $email, string $role): array
    {
        return [
            'name' => 'Person', 'email' => $email,
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
            'role' => $role,
        ];
    }

    private function assertEmployee(User $user): void
    {
        $this->assertSame(UserType::USER->value, $user->type);
        $this->assertSame([UserRole::EMPLOYEE->value], $user->getRoleNames()->all());
        $this->assertSame(AccountType::EMPLOYEE, $user->accountType());
    }

    private function ownWorkspace(User $owner): Workspace
    {
        $workspace = Workspace::create(['name' => 'WS ' . $owner->id, 'created_by' => $owner->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($owner)->save();
        DB::table('user_workspaces')->insert(['user_id' => $owner->id, 'workspace_id' => $workspace->id, 'permission' => 'Owner']);
        $owner->forceFill(['currant_workspace' => $workspace->id])->save();

        return $workspace->fresh();
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('users.csv', $content);
    }
}

<?php

namespace Tests\Feature\Provisioning;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\PodUser\State;
use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Status;
use App\Models\Mail\SendLoginDetail;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Services\Membership\ProjectMembershipService;
use App\Services\PodService;
use App\Services\PodUserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/** UNA-012: every provisioning source produces the same account structure. */
class ProvisioningSourcesTest extends ProvisioningTestCase
{
    public function test_web_company_registration(): void
    {
        $this->post('/register', $this->registration('company@example.test', 'company'))->assertRedirect(route('company.onboarding.edit'));

        $this->assertAccountStructure(User::where('email', 'company@example.test')->sole(), UserRole::COMPANY, 'company.onboarding.edit');
        Mail::assertSent(SendLoginDetail::class, fn ($mail) => $mail->password === null); // never echoes the chosen password
    }

    public function test_web_employee_registration(): void
    {
        $this->post('/register', $this->registration('employee@example.test', 'employee'))->assertRedirect(route('my-profile.edit'));

        $this->assertAccountStructure(User::where('email', 'employee@example.test')->sole(), UserRole::EMPLOYEE, 'my-profile.edit');
        auth()->logout();
        $this->post('/register', $this->registration('x@example.test', 'administrator'))->assertSessionHasErrors('role');
        $this->assertSame(0, User::where('email', 'x@example.test')->count());
    }

    public function test_api_registration_creates_a_complete_employee_and_a_safe_response(): void
    {
        $response = $this->postJson('/api/register', ['name' => 'Api', 'email' => 'api@example.test', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!'])
            ->assertCreated()
            ->assertJsonPath('data.account_type', 'employee')
            ->assertJsonPath('data.onboarding_required', true)
            ->assertJsonPath('data.onboarding_type', 'employee')
            ->assertJsonPath('data.completion_percentage', 0)
            ->assertJsonPath('data.onboarding_route', 'my-profile.edit')
            ->assertJsonPath('data.onboarding_url', route('my-profile.edit'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(['id', 'name', 'email'], array_keys($response->json('data.user')));

        $user = User::where('email', 'api@example.test')->sole();
        $workspace = $this->assertAccountStructure($user, UserRole::EMPLOYEE, 'my-profile.edit');
        $this->assertSame($workspace->id, $response->json('data.workspace_id'));

        $this->postJson('/api/register', ['name' => 'X', 'email' => 'role@example.test', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'role' => 'company'])
            ->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_api_provisioning_failure_creates_no_user_and_no_token(): void
    {
        DB::table('plans')->delete();

        $this->postJson('/api/register', ['name' => 'Api', 'email' => 'api@example.test', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!'])
            ->assertStatus(500)->assertJsonPath('message', __('Accounts cannot be created right now because the default plan is missing. Please contact the administrator.'));
        $this->assertSame([0, 0], [User::where('email', 'api@example.test')->count(), DB::table('personal_access_tokens')->count()]);
    }

    public function test_administrator_created_company(): void
    {
        $admin = $this->account('administrator', 'admin@example.test');

        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Acme', 'workspace' => 'Acme HQ', 'email' => ' Owner@Acme.TEST ', 'password_switch' => 'on', 'password' => 'secret12'])
            ->assertRedirect(route('users.index', ''));

        $user = User::where('email', 'owner@acme.test')->sole();
        $workspace = $this->assertAccountStructure($user, UserRole::COMPANY, 'company.onboarding.edit');
        $this->assertSame('Acme HQ', $workspace->name);
        $this->assertSame(1, (int) $user->is_enable_login);
        Mail::assertSent(SendLoginDetail::class, fn ($mail) => $mail->hasTo('owner@acme.test') && $mail->password === 'secret12');
        $this->actingAs($this->account('company', 'c@example.test'))->post(route('users.store'), ['name' => 'X', 'workspace' => 'X', 'email' => 'x@example.test'])->assertForbidden();
    }

    public function test_csv_company_and_employee_imports(): void
    {
        $admin = $this->account('administrator', 'admin@example.test');
        $csv = "name,email,password,type\nAcme,acme@example.test,secret12,company\nAva, Ava@Example.test ,secret12,employee\nLegacy,legacy@example.test,secret12,\n";

        $this->actingAs($admin)->post(route('user.import'), ['file' => UploadedFile::fake()->createWithContent('u.csv', $csv)])->assertSessionHas('success');

        $this->assertAccountStructure(User::where('email', 'acme@example.test')->sole(), UserRole::COMPANY, 'company.onboarding.edit');
        $this->assertAccountStructure(User::where('email', 'ava@example.test')->sole(), UserRole::EMPLOYEE, 'my-profile.edit');
        $this->assertAccountStructure(User::where('email', 'legacy@example.test')->sole(), UserRole::COMPANY, 'company.onboarding.edit');
    }

    public function test_workspace_invited_new_user_is_a_pending_employee_with_an_owned_workspace(): void
    {
        $company = $this->account('company', 'owner@example.test');
        $target = $company->ownWorkspace();

        $this->actingAs($company)->post(route('users.invite.update', $target->slug), ['username' => 'Ivy', 'useremail' => 'ivy@example.test', 'userpassword' => 'secret12', 'password_switch' => 'on'])
            ->assertOk()->assertJsonPath('code', 200);

        $ivy = User::where('email', 'ivy@example.test')->sole();
        $owned = $this->assertAccountStructure($ivy, UserRole::EMPLOYEE, 'my-profile.edit');
        $this->assertNotSame($target->id, $owned->id);
        $invite = UserWorkspace::where('user_id', $ivy->id)->where('workspace_id', $target->id)->sole();
        $this->assertSame(['Member', Status::EMPLOYEE_PENDING->value], [$invite->permission, $invite->status]);
        Mail::assertSent(SendLoginDetail::class, 1);
    }

    public function test_pod_invited_new_user_is_an_employee_with_matched_membership(): void
    {
        $company = $this->account('company', 'owner@example.test');
        $this->completeBusinessProfile($company);
        $pod = app(PodService::class)->createDraft($company->fresh(), ['title' => 'Pod']);

        app(PodUserService::class)->inviteNewUser(['name' => 'Pat', 'email' => 'Pat@Example.test', 'password' => 'secret12'], $pod->id, [], $company);

        $pat = User::where('email', 'pat@example.test')->sole();
        $this->assertAccountStructure($pat, UserRole::EMPLOYEE, 'my-profile.edit');
        $membership = PodUser::where('pod_id', $pod->id)->where('user_id', $pat->id)->sole();
        $this->assertSame(State::MATCHED, $membership->state);
        $this->assertSame(1, DB::table('pod_user_state_histories')->where('pod_user_id', $membership->id)->count());
        $this->assertSame(1, UserWorkspace::where('user_id', $pat->id)->where('workspace_id', $pod->workspace->id)->count());
    }

    public function test_project_invited_new_user_is_an_employee_with_project_and_workspace_memberships(): void
    {
        $company = $this->account('company', 'owner@example.test');
        $project = Project::forceCreate(['name' => 'Apollo', 'workspace' => $company->ownWorkspace()->id, 'created_by' => $company->id, 'is_active' => 1]);

        $result = app(ProjectMembershipService::class)->inviteByEmail(' Pia@Example.TEST', $project, 'Member', $company);

        $this->assertTrue($result->applied);
        $pia = User::where('email', 'pia@example.test')->sole();
        $this->assertAccountStructure($pia, UserRole::EMPLOYEE, 'my-profile.edit');
        $this->assertSame(1, UserProject::where('user_id', $pia->id)->where('project_id', $project->id)->count());
        $this->assertSame(1, UserWorkspace::where('user_id', $pia->id)->where('workspace_id', $company->ownWorkspace()->id)->count());
        Mail::assertSent(SendLoginDetail::class, fn ($mail) => is_string($mail->password) && strlen($mail->password) === 12);

        // The owner adding themselves is allowed and creates no account.
        app(ProjectMembershipService::class)->inviteByEmail('owner@example.test', $project, 'Owner', $company);
        $this->assertSame(1, User::where('email', 'owner@example.test')->count());
    }

    public function test_fortify_cannot_bypass_provisioning(): void
    {
        $action = app(CreateNewUser::class);
        try {
            $action->create(['name' => 'F', 'email' => 'f@example.test', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!']);
            $this->fail('created without a role');
        } catch (ValidationException) {
            $this->assertSame(0, User::where('email', 'f@example.test')->count());
        }

        $user = $action->create(['name' => 'F', 'email' => 'F@example.test', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'role' => 'employee']);
        $this->assertAccountStructure($user, UserRole::EMPLOYEE, 'my-profile.edit');
    }

    private function registration(string $email, string $role): array
    {
        return ['name' => 'Person', 'email' => $email, 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'role' => $role];
    }
}

<?php

namespace Tests\Feature\Company;

use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-010: registration, email verification and login (/check) send an incomplete company to
 * /company/onboarding; employees keep UNA-007 precedence; nothing else is redirected.
 */
class CompanyOnboardingRedirectTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Mail::fake();
        Bus::fake();
    }

    public function test_new_company_registration_redirects_to_onboarding(): void
    {
        $this->post('/register', $this->registration('company@example.test', 'company'))
            ->assertRedirect(route('company.onboarding.edit'));
        auth()->logout();

        $this->post('/register', $this->registration('employee@example.test', 'employee'))
            ->assertRedirect(route('my-profile.edit'));
    }

    public function test_newly_verified_company_redirects_to_onboarding(): void
    {
        $company = $this->account('company', ['email_verified_at' => null]);

        $this->actingAs($company)->get(route('company.onboarding.edit'))->assertRedirect(route('verification.notice'));
        $this->flushSession();
        $this->actingAs($company)->get($this->verificationUrl($company))
            ->assertRedirect(route('company.onboarding.edit', ['verified' => 1]));
    }

    public function test_login_check_redirect_precedence(): void
    {
        $incompleteCompany = $this->account('company');
        $completeCompany = $this->account('company');
        $this->completeBusinessProfile($completeCompany);
        $incompleteEmployee = $this->account('employee');

        $this->actingAs($incompleteCompany)->get('/check')
            ->assertRedirect(route('company.onboarding.edit'))
            ->assertSessionHas('error', __('Please complete your company profile. :required% is required to publish a POD.', ['required' => 100]));
        $this->actingAs($completeCompany)->get('/check')->assertRedirect(route('home'));
        $this->actingAs($incompleteEmployee)->get('/check')->assertRedirect(route('my-profile.edit'));
        $this->actingAs($this->account('administrator'))->get('/check')->assertRedirect(route('home'));
    }

    public function test_incomplete_companies_are_not_redirected_away_from_unrelated_areas(): void
    {
        $company = $this->account('company');

        $this->actingAs($company)->getJson('/api/employees')->assertOk();
        foreach (['home', 'users.my.account', 'pods.index', 'my-profile.edit'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertNotContains('profile.complete', $middleware, $name);
        }
    }

    // ================================================================ helpers

    private function account(string $type, array $attributes = []): User
    {
        $user = User::factory()->{$type}()->create(array_merge(['plan' => 1, 'email_verified_at' => now()], $attributes));
        $workspace = Workspace::create(['name' => "{$user->name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner']);
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return $user->fresh();
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }

    private function registration(string $email, string $role): array
    {
        return ['name' => 'Person', 'email' => $email, 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'role' => $role];
    }
}

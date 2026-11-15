<?php

namespace Tests\Feature\Profile;

use App\Models\JobVacancy;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\JobApplicationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-007: onboarding redirects (registration, email verification, /check after login) and the
 * profile-completion gate on job pages, job APIs and job applications. In-memory SQLite only.
 *
 * The landing job views are replaced by stubs (a temporary view path) so these tests exercise the
 * routes, middleware and controllers without the landing layout's dependencies.
 */
class OnboardingAndJobAccessTest extends TestCase
{
    use UsesInMemorySqlite;

    private string $stubViews;
    private JobVacancy $vacancy;
    private User $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Schema::table('projects', fn (Blueprint $t) => $t->boolean('private')->default(false));
        Schema::table('job_vacancies', function (Blueprint $t) {
            $t->text('description')->nullable();
            $t->decimal('salary', 10, 2)->nullable();
        });
        Schema::create('job_vacancy_skill', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('job_vacancy_id');
            $t->unsignedBigInteger('skill_id');
            $t->timestamps();
        });
        Schema::create('job_applications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('job_vacancy_id');
            $t->unsignedBigInteger('user_id');
            $t->string('status')->default('pending');
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Mail::fake();
        Bus::fake();

        $this->stubViews = sys_get_temp_dir() . '/una007-views-' . uniqid();
        foreach (['find_work/index', 'job_vacancies/landing/list', 'job_vacancies/landing/page'] as $view) {
            File::ensureDirectoryExists(dirname("{$this->stubViews}/{$view}.blade.php"));
            File::put("{$this->stubViews}/{$view}.blade.php", "stub:{$view}");
        }
        $this->app['view']->getFinder()->prependLocation($this->stubViews);

        $this->company = $this->account('company');
        $this->vacancy = JobVacancy::create(['user_id' => $this->company->id, 'title' => 'Backend developer']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->stubViews);
        parent::tearDown();
    }

    // ================================================================ onboarding

    public function test_new_employee_registration_without_verification_lands_on_my_profile(): void
    {
        $this->post('/register', $this->registration('new-employee@example.test', 'employee'))
            ->assertRedirect(route('my-profile.edit'));
        auth()->logout();

        $this->post('/register', $this->registration('new-company@example.test', 'company'))
            ->assertRedirect('/home');
    }

    public function test_email_verification_sends_a_new_employee_to_my_profile(): void
    {
        $employee = $this->account('employee', ['email_verified_at' => null]);
        $company = $this->account('company', ['email_verified_at' => null]);

        // Unverified employees are sent to verification before /my-profile (verified group).
        $this->actingAs($employee)->get(route('my-profile.edit'))->assertRedirect(route('verification.notice'));

        $this->actingAs($employee)->get($this->verificationUrl($employee))
            ->assertRedirect(route('my-profile.edit', ['verified' => 1]));
        $this->assertTrue($employee->fresh()->hasVerifiedEmail());

        $this->flushSession(); // separate browser: no intended URL carried over from the employee
        $this->actingAs($company)->get($this->verificationUrl($company))->assertRedirect('/home?verified=1');
    }

    public function test_login_check_redirects_only_incomplete_employees_to_my_profile(): void
    {
        $incomplete = $this->account('employee');
        $complete = $this->account('employee');
        $this->completeEmployeeProfile($complete);

        $this->actingAs($incomplete)->get('/check')
            ->assertRedirect(route('my-profile.edit'))->assertSessionHas('error');
        $this->actingAs($complete)->get('/check')->assertRedirect(route('home'));
        $this->actingAs($this->company)->get('/check')->assertRedirect(route('home'));
        $this->actingAs($this->account('administrator'))->get('/check')->assertRedirect(route('home'));
    }

    public function test_incomplete_employees_are_not_redirected_away_from_unrelated_areas(): void
    {
        $incomplete = $this->account('employee');

        // No global redirect loop: an unrelated authenticated endpoint still answers normally.
        $this->actingAs($incomplete)->getJson('/api/employees')->assertOk();

        $middleware = app('router')->getRoutes()->getByName('my-profile.edit')->gatherMiddleware();
        $this->assertNotContains('profile.complete', $middleware);
        foreach (['home', 'users.my.account', 'pods.index'] as $name) {
            $this->assertNotContains('profile.complete', app('router')->getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    // ================================================================ job access

    public function test_incomplete_employees_are_redirected_from_job_pages(): void
    {
        $incomplete = $this->account('employee');

        foreach (['/find-work', '/job-vacancies/list', "/job-vacancies/{$this->vacancy->id}"] as $url) {
            $this->actingAs($incomplete)->get($url)
                ->assertRedirect(route('my-profile.edit'))
                ->assertSessionHas('error', __('Complete at least :required% of your profile to access jobs. Your profile is :percentage% complete.', ['required' => 80, 'percentage' => 0]));
        }

        $this->actingAs($incomplete)->getJson('/find-work')->assertForbidden()->assertJson([
            'completion_percentage' => 0, 'required_percentage' => 80, 'profile_url' => route('my-profile.edit'),
        ]);
    }

    public function test_incomplete_employees_cannot_use_job_apis_or_apply(): void
    {
        $incomplete = $this->account('employee');
        $expected = ['completion_percentage' => 0, 'required_percentage' => 80, 'profile_url' => route('my-profile.edit')];

        $this->actingAs($incomplete)->getJson('/api/job-vacancies')->assertForbidden()->assertJson($expected)->assertJsonStructure(['message']);
        $this->actingAs($incomplete)->getJson("/api/job-vacancies/{$this->vacancy->id}")->assertForbidden()->assertJson($expected);
        $this->actingAs($incomplete)->postJson('/api/job-applications', ['job_vacancy_id' => $this->vacancy->id])->assertForbidden()->assertJson($expected);
        $this->assertSame(0, DB::table('job_applications')->count());

        // The service refuses too, whatever the entry point.
        try {
            app(JobApplicationService::class)->createApplication(['user_id' => $incomplete->id, 'job_vacancy_id' => $this->vacancy->id]);
            $this->fail('application accepted');
        } catch (\Exception $e) {
            $this->assertSame(__('Complete at least :required% of your profile before applying for jobs.', ['required' => 80]), $e->getMessage());
        }
        $this->assertSame(0, DB::table('job_applications')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_complete_employees_companies_and_administrators_keep_job_access(): void
    {
        $complete = $this->account('employee');
        $this->completeEmployeeProfile($complete);
        $admin = $this->account('administrator');

        foreach ([$complete, $this->company, $admin] as $account) {
            $this->actingAs($account)->get('/job-vacancies/list')->assertOk()->assertSee('stub:job_vacancies/landing/list');
            $this->actingAs($account)->get("/job-vacancies/{$this->vacancy->id}")->assertOk();
            $this->actingAs($account)->getJson('/api/job-vacancies')->assertOk();
            $this->actingAs($account)->getJson("/api/job-vacancies/{$this->vacancy->id}")->assertOk()->assertJsonPath('id', $this->vacancy->id);
        }

        $this->actingAs($complete)->postJson('/api/job-applications', ['job_vacancy_id' => $this->vacancy->id])->assertCreated();
        $this->assertSame(1, DB::table('job_applications')->where('user_id', $complete->id)->count());
    }

    public function test_guests_can_still_open_the_public_job_pages(): void
    {
        $this->get('/find-work')->assertOk()->assertSee('stub:find_work/index');
        $this->get('/job-vacancies/list')->assertOk()->assertSee('stub:job_vacancies/landing/list');
        $this->get("/job-vacancies/{$this->vacancy->id}")->assertOk()->assertSee('stub:job_vacancies/landing/page');
        // The API stays authenticated as before.
        $this->getJson('/api/job-vacancies')->assertUnauthorized();
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

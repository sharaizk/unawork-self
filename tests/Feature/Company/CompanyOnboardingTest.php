<?php

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Models\WorkspaceWorkerRequirement;
use App\Services\Company\BusinessOnboardingCompletionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-010: the /company/onboarding page - drafts, completion, later edits, ownership, worker
 * requirements, logo replacement and the closed legacy settings write path. In-memory SQLite only.
 */
class CompanyOnboardingTest extends TestCase
{
    use UsesInMemorySqlite;

    private User $company;
    private Workspace $workspace;
    private Industry $industry;
    private JobTitle $role;
    private Skill $php;
    private Skill $sql;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->createAdminLayoutSchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Storage::fake('public');

        $this->industry = Industry::create(['name' => 'Technology']);
        $this->role = JobTitle::create(['title' => 'Backend Developer']);
        $this->php = Skill::create(['name' => 'PHP']);
        $this->sql = Skill::create(['name' => 'SQL']);
        $this->company = $this->account('company');
        $this->workspace = $this->company->ownWorkspace();
    }

    public function test_the_page_renders_for_the_company_and_tracks_that_onboarding_started(): void
    {
        $this->assertNull($this->workspace->business_onboarding_started_at);

        $page = $this->actingAs($this->company)->get(route('company.onboarding.edit'))->assertOk();

        $page->assertSee(__('Company Onboarding'))
            ->assertSee('0<small>%</small>', false)
            ->assertSee(__('A :required% complete company profile is required to publish a POD. Save a draft at any time and come back later.', ['required' => 100]))
            ->assertSee('data-co-step="requirements"', false)
            ->assertSee('id="co-requirement-template"', false)
            ->assertSee('assets/custom/js/company-onboarding.js', false)
            ->assertSee(e(__('Enter a valid contact email')), false);
        $this->assertNotNull($this->workspace->fresh()->business_onboarding_started_at);
    }

    public function test_company_saves_a_draft_then_completes_and_later_edits(): void
    {
        $this->save(['company' => 'Acme', 'contact_name' => 'Casey', 'step' => 'company', 'intent' => 'continue'])
            ->assertRedirect(route('company.onboarding.edit', ['step' => 'contact']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $workspace = $this->workspace->fresh();
        $this->assertSame(['Acme', 'Casey', 'company'], [$workspace->company, $workspace->contact_name, $workspace->business_onboarding_step]);
        $this->assertNull($workspace->business_onboarding_completed_at);

        // Complete everything (the logo upload included).
        $this->save($this->completePayload() + ['logo' => UploadedFile::fake()->image('logo.png', 120, 120), 'step' => 'review'])
            ->assertSessionHasNoErrors();
        $workspace = $this->workspace->fresh();
        $this->assertSame(100, app(BusinessOnboardingCompletionService::class)->percentage($workspace));
        $this->assertNotNull($completedAt = $workspace->business_onboarding_completed_at);
        $this->assertSame('review', $workspace->business_onboarding_step);
        Storage::disk('public')->assertExists('logo/' . $workspace->logo);

        $this->actingAs($this->company)->get(route('company.onboarding.edit'))->assertOk()->assertSee(__('Company Profile'));

        // Later edit: the profile becomes incomplete again; completed_at stays as history.
        $this->save(['description' => 'Short now', 'step' => 'description'])->assertSessionHasNoErrors();
        $this->assertSame(90, app(BusinessOnboardingCompletionService::class)->percentage($this->workspace->fresh()));
        $this->assertEquals($completedAt, $this->workspace->fresh()->business_onboarding_completed_at);
    }

    public function test_worker_requirements_and_skills_sync_and_foreign_ids_are_rejected(): void
    {
        $this->save(['requirements' => [
            ['job_title_id' => $this->role->id, 'headcount' => 2, 'skill_ids' => [$this->php->id, $this->sql->id], 'preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract', 'is_active' => 1],
            ['job_title_id' => '', 'headcount' => '', 'location' => '', 'description' => ''], // blank row ignored
        ]])->assertSessionHasNoErrors();
        $requirement = WorkspaceWorkerRequirement::where('workspace_id', $this->workspace->id)->sole();
        $this->assertEqualsCanonicalizing([$this->php->id, $this->sql->id], $requirement->skills->pluck('id')->all());

        // Update by id, add a second one, then remove the first.
        $this->save(['requirements' => [
            ['id' => $requirement->id, 'job_title_id' => $this->role->id, 'headcount' => 3, 'skill_ids' => [$this->sql->id], 'is_active' => 1],
            ['job_title_id' => $this->role->id, 'headcount' => 1, 'skill_ids' => [$this->php->id], 'minimum_skill_proficiency' => 'expert', 'minimum_years_experience' => 2.5],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([3, [$this->sql->id]], [$requirement->fresh()->headcount, $requirement->fresh()->skills->pluck('id')->all()]);
        $this->assertSame(2, WorkspaceWorkerRequirement::where('workspace_id', $this->workspace->id)->count());

        $this->save(['requirements' => []])->assertSessionHasNoErrors();
        $this->assertSame([0, 0], [WorkspaceWorkerRequirement::count(), DB::table('workspace_worker_requirement_skill')->count()]);

        // Another company's requirement id is rejected and nothing changes.
        $other = $this->account('company');
        $theirs = new WorkspaceWorkerRequirement(['job_title_id' => $this->role->id, 'headcount' => 1]);
        $theirs->forceFill(['workspace_id' => $other->ownWorkspace()->id])->save();
        $this->save(['requirements' => [['id' => $theirs->id, 'job_title_id' => $this->role->id, 'headcount' => 9]]])
            ->assertSessionHasErrors('requirements.0.id');
        $this->assertSame(1, $theirs->fresh()->headcount);
        $this->assertSame(0, WorkspaceWorkerRequirement::where('workspace_id', $this->workspace->id)->count());
    }

    public function test_a_company_can_only_edit_its_own_workspace(): void
    {
        $other = $this->account('company');
        $otherWorkspace = $other->ownWorkspace();

        // Injected ownership fields are ignored; only the authenticated company's workspace changes.
        $this->save(['company' => 'Mine', 'workspace_id' => $otherWorkspace->id, 'created_by' => $other->id,
            'ownerable_type' => 'App\\Models\\Pod', 'ownerable_id' => 99])->assertSessionHasNoErrors();
        $this->assertSame('Mine', $this->workspace->fresh()->company);
        $this->assertNull($otherWorkspace->fresh()->company);
        $this->assertSame([User::class, $this->company->id], [$this->workspace->fresh()->ownerable_type, (int) $this->workspace->fresh()->ownerable_id]);
        $this->assertFalse($this->company->can('manageCompanyProfile', $otherWorkspace));
    }

    public function test_employees_administrators_clients_and_guests_cannot_use_company_onboarding(): void
    {
        foreach (['employee', 'administrator'] as $type) {
            $account = $this->account($type);
            $this->actingAs($account)->get(route('company.onboarding.edit'))->assertForbidden();
            $this->actingAs($account)->post(route('company.onboarding.update'), ['company' => 'X'])->assertForbidden();
        }
        auth()->logout();

        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'x']);
        $this->actingAs($client, 'client');
        $this->app['auth']->shouldUse('web');
        $this->get(route('company.onboarding.edit'))->assertRedirect(route('login'));
        $this->post(route('company.onboarding.update'), ['company' => 'X'])->assertRedirect(route('login'));
        $this->assertSame(0, Workspace::where('company', 'X')->count());
    }

    public function test_validation_is_field_specific_and_keeps_old_input(): void
    {
        $this->from(route('company.onboarding.edit'))->save([
            'company' => 'Keep me',
            'industry_id' => 999,
            'contact_email' => 'nope',
            'website' => 'javascript:alert(1)',
            'linkedin' => 'not a url',
            'matching_mode' => 'psychic',
            'default_pod_application_approval' => 'sometimes',
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            'requirements' => [['job_title_id' => 999, 'headcount' => 0, 'skill_ids' => [$this->php->id, $this->php->id], 'preferred_work_mode' => 'space', 'minimum_years_experience' => 99]],
        ])->assertRedirect(route('company.onboarding.edit'))->assertSessionHasErrors([
            'industry_id', 'contact_email', 'website', 'linkedin', 'matching_mode', 'default_pod_application_approval', 'logo',
            'requirements.0.job_title_id', 'requirements.0.headcount', 'requirements.0.skill_ids.0', 'requirements.0.preferred_work_mode', 'requirements.0.minimum_years_experience',
        ])->assertSessionHasInput('company', 'Keep me');
        $this->assertNull($this->workspace->fresh()->company);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_failed_save_rolls_back_everything_and_keeps_the_old_logo(): void
    {
        $this->save(['company' => 'Acme', 'logo' => UploadedFile::fake()->image('first.png')])->assertSessionHasNoErrors();
        $first = $this->workspace->fresh()->logo;

        DB::statement("CREATE TRIGGER fail_requirement BEFORE INSERT ON workspace_worker_requirements BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
        $this->save([
            'company' => 'Renamed', 'logo' => UploadedFile::fake()->image('second.png'),
            'requirements' => [['job_title_id' => $this->role->id, 'headcount' => 1, 'skill_ids' => [$this->php->id]]],
        ])->assertSessionHas('error');
        DB::statement('DROP TRIGGER fail_requirement');

        $workspace = $this->workspace->fresh();
        $this->assertSame(['Acme', $first], [$workspace->company, $workspace->logo]);
        $this->assertSame(['logo/' . $first], Storage::disk('public')->allFiles());
        $this->assertSame(0, WorkspaceWorkerRequirement::count());

        // A successful replacement deletes the previous file after commit.
        $this->save(['logo' => UploadedFile::fake()->image('third.png')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing('logo/' . $first);
        Storage::disk('public')->assertExists('logo/' . $this->workspace->fresh()->logo);
    }

    public function test_the_legacy_settings_form_no_longer_writes_company_fields(): void
    {
        $this->actingAs($this->company)->post(route('workspace.settings.store', $this->workspace->slug), [
            'company' => 'Unvalidated', 'website' => 'javascript:alert(1)', 'industry_id' => 999,
        ])->assertRedirect(route('company.onboarding.edit'));

        $this->assertNull($this->workspace->fresh()->company);
        $this->assertNull($this->workspace->fresh()->website);
    }

    // ================================================================ helpers

    private function completePayload(): array
    {
        return [
            'company' => 'Acme Ltd', 'industry_id' => $this->industry->id,
            'contact_name' => 'Casey', 'contact_email' => 'casey@acme.example.test', 'contact_position' => 'CTO',
            'telephone' => '+94 11 000 0000', 'city' => 'Colombo', 'country' => 'Sri Lanka',
            'website' => 'https://acme.example.test', 'linkedin' => 'https://www.linkedin.com/company/acme',
            'description' => str_repeat('Acme builds products with remote teams. ', 4),
            'matching_mode' => 'assisted', 'default_pod_application_approval' => 'manual', 'default_inner_circle_approval' => 'automatic',
            'requirements' => [['job_title_id' => $this->role->id, 'headcount' => 2, 'skill_ids' => [$this->php->id], 'preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract', 'is_active' => 1]],
        ];
    }

    private function save(array $data)
    {
        // The real form always sends the full requirement list with requirements_present=1.
        $present = array_key_exists('requirements', $data) ? ['requirements_present' => 1] : [];

        return $this->actingAs($this->company)->post(route('company.onboarding.update'), $present + $data);
    }

    private function account(string $type): User
    {
        $user = User::factory()->{$type}()->create(['plan' => 1, 'email_verified_at' => now()]);
        $workspace = Workspace::create(['name' => "{$user->name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner']);
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return $user->fresh();
    }
}

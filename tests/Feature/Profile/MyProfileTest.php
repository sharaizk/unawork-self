<?php

namespace Tests\Feature\Profile;

use App\Models\JobTitle;
use App\Models\Profile;
use App\Models\ProfileExperience;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-007: /my-profile saving - drafts, nested record ownership, validation and safe CV
 * replacement. In-memory SQLite only.
 */
class MyProfileTest extends TestCase
{
    use UsesInMemorySqlite;

    private const SYNC_ALL = ['skills', 'experiences', 'educations', 'certifications', 'portfolios', 'preferred_roles', 'project_types'];

    private User $employee;
    private User $other;
    private JobTitle $jobTitle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Storage::fake('public');

        $this->jobTitle = JobTitle::create(['title' => 'Developer']);
        $this->employee = $this->account('employee');
        $this->other = $this->account('employee');
    }

    public function test_employee_can_save_an_incomplete_draft_and_come_back(): void
    {
        $this->actingAs($this->employee)->post(route('my-profile.update'), [
            'sync' => self::SYNC_ALL,
            'about' => 'Draft summary',
            'timezone' => 'Europe/Berlin',
        ])->assertRedirect(route('my-profile.edit'))->assertSessionHasNoErrors()->assertSessionHas('success');

        $profile = Profile::where('user_id', $this->employee->id)->sole();
        $this->assertSame(['Draft summary', 'Europe/Berlin', null, null], [$profile->about, $profile->timezone, $profile->country, $profile->years_experience]);

        // Returning later: a second save updates the same row and keeps earlier answers.
        $this->actingAs($this->employee)->post(route('my-profile.update'), [
            'about' => 'Draft summary', 'timezone' => 'Europe/Berlin', 'country' => 'Germany', 'city' => 'Berlin',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Profile::where('user_id', $this->employee->id)->count());
        $this->assertSame(20, app(\App\Services\Profile\ProfileCompletionService::class)->percentage($this->employee->fresh())); // summary + location
    }

    public function test_employee_creates_updates_and_deletes_their_own_nested_records(): void
    {
        [$php, $sql, $css] = [Skill::create(['name' => 'PHP']), Skill::create(['name' => 'SQL']), Skill::create(['name' => 'CSS'])];
        $designer = JobTitle::create(['title' => 'Designer']);

        $this->save($this->employee, [
            'skills' => [['skill_id' => $php->id, 'proficiency' => 'expert'], ['skill_id' => $sql->id, 'proficiency' => 'advanced']],
            'experiences' => [
                ['employer' => 'Acme', 'job_title' => 'Developer', 'start_date' => '2020-01-01', 'end_date' => '2022-01-01', 'engagement_type' => 'full_time'],
                ['employer' => 'Beta', 'job_title' => 'Lead', 'start_date' => '2022-02-01', 'is_current' => '1'],
                ['employer' => '', 'job_title' => '', 'description' => ''], // blank row is ignored
            ],
            'educations' => [['institution' => 'University', 'start_year' => 2014, 'end_year' => 2018]],
            'certifications' => [['name' => 'AWS', 'issue_date' => '2021-05-01', 'expiration_date' => '2024-05-01']],
            'portfolios' => [['title' => 'Site', 'url' => 'https://example.test']],
            'preferred_role_ids' => [$this->jobTitle->id, $designer->id],
            'project_types' => ['hourly', 'long_term'],
        ])->assertSessionHasNoErrors();

        $profile = Profile::with(Profile::DETAIL_RELATIONS)->where('user_id', $this->employee->id)->sole();
        $this->assertSame(['Beta', 'Acme'], $profile->experiences->pluck('employer')->all()); // current first
        $this->assertCount(1, $profile->educations);
        $this->assertCount(1, $profile->certifications);
        $this->assertCount(1, $profile->portfolios);
        $this->assertEqualsCanonicalizing([$this->jobTitle->id, $designer->id], $profile->preferredRoles->pluck('job_title_id')->all());
        $this->assertEqualsCanonicalizing(['hourly', 'long_term'], $profile->projectTypes->map(fn ($t) => $t->project_type->value)->all());
        $this->assertEquals([$php->id => 'expert', $sql->id => 'advanced'], $this->employee->fresh()->skills->pluck('pivot.proficiency', 'id')->all());

        $acme = $profile->experiences->firstWhere('employer', 'Acme');
        $beta = $profile->experiences->firstWhere('employer', 'Beta');
        $this->assertTrue($beta->is_current);
        $this->assertNull($beta->end_date);

        // Update Acme by id, delete Beta (omitted), replace skills and sets.
        $this->save($this->employee, [
            'skills' => [['skill_id' => $sql->id, 'proficiency' => 'expert'], ['skill_id' => $css->id, 'proficiency' => 'beginner']],
            'experiences' => [['id' => $acme->id, 'employer' => 'Acme Corp', 'job_title' => 'Senior Developer']],
            'preferred_role_ids' => [$designer->id],
            'project_types' => ['hourly'],
        ])->assertSessionHasNoErrors();

        $profile->refresh()->load(Profile::DETAIL_RELATIONS);
        $this->assertSame([[$acme->id, 'Acme Corp', 'Senior Developer']], $profile->experiences->map(fn ($e) => [$e->id, $e->employer, $e->job_title])->all());
        $this->assertFalse(ProfileExperience::whereKey($beta->id)->exists());
        $this->assertCount(0, $profile->educations, 'omitted from the submission = removed');
        $this->assertSame([$designer->id], $profile->preferredRoles->pluck('job_title_id')->all());
        $this->assertSame(['hourly'], $profile->projectTypes->map(fn ($t) => $t->project_type->value)->all());
        $this->assertEquals([$sql->id => 'expert', $css->id => 'beginner'], $this->employee->fresh()->skills->pluck('pivot.proficiency', 'id')->all());

        // A collection not named in `sync` is left untouched.
        $this->actingAs($this->employee)->post(route('my-profile.update'), ['sync' => ['portfolios'], 'about' => 'x'])->assertSessionHasNoErrors();
        $this->assertCount(1, $profile->refresh()->experiences);
    }

    public function test_another_employee_cannot_update_or_delete_someone_elses_records(): void
    {
        $this->save($this->other, [
            'experiences' => [['employer' => 'Theirs', 'job_title' => 'Owner']],
            'portfolios' => [['title' => 'Their site', 'url' => 'https://their.example.test']],
        ]);
        $theirs = Profile::with(Profile::DETAIL_RELATIONS)->where('user_id', $this->other->id)->sole();
        $this->save($this->employee, ['experiences' => [['employer' => 'Mine', 'job_title' => 'Me']]]);
        $before = $this->rows();

        // Updating a foreign id is rejected by validation and changes nothing.
        $this->save($this->employee, [
            'experiences' => [['id' => $theirs->experiences[0]->id, 'employer' => 'Hijacked', 'job_title' => 'X']],
            'portfolios' => [['id' => $theirs->portfolios[0]->id, 'title' => 'Hijacked', 'url' => 'https://x.example.test']],
        ])->assertSessionHasErrors(['experiences.0.id', 'portfolios.0.id']);
        $this->assertEquals($before, $this->rows());

        // Saving their own (empty) collections never deletes another profile's rows.
        $this->save($this->employee, [])->assertSessionHasNoErrors();
        $this->assertSame('Theirs', ProfileExperience::where('profile_id', $theirs->id)->value('employer'));
        $this->assertSame(1, DB::table('profile_portfolios')->where('profile_id', $theirs->id)->count());

        // user_id / profile_id from the browser are ignored.
        $this->save($this->employee, ['user_id' => $this->other->id, 'profile_id' => $theirs->id, 'about' => 'Mine only'])->assertSessionHasNoErrors();
        $this->assertSame('Mine only', Profile::where('user_id', $this->employee->id)->value('about'));
        $this->assertNull(Profile::where('user_id', $this->other->id)->value('about'));
    }

    public function test_validation_rejects_bad_input_with_field_errors_and_old_input(): void
    {
        $skill = Skill::create(['name' => 'PHP']);

        $this->from(route('my-profile.edit'))->save($this->employee, [
            'about' => 'Keep me',
            'linkedin_url' => 'javascript:alert(1)',
            'website' => 'not a url',
            'timezone' => 'Mars/Olympus',
            'years_experience' => 61,
            'hours_per_week' => 100,
            'availability_status' => 'sometimes',
            'preferred_work_mode' => 'space',
            'skills' => [['skill_id' => $skill->id, 'proficiency' => 'guru'], ['skill_id' => $skill->id, 'proficiency' => 'expert']],
            'experiences' => [
                ['employer' => 'Acme', 'job_title' => 'Dev', 'start_date' => '2022-01-01', 'end_date' => '2021-01-01'],
                ['employer' => 'Beta', 'job_title' => 'Dev', 'is_current' => '1', 'end_date' => '2021-01-01'],
                ['employer' => 'Gamma'],
            ],
            'educations' => [['institution' => 'Uni', 'start_year' => 2020, 'end_year' => 2019]],
            'certifications' => [['name' => 'AWS', 'issue_date' => '2022-01-01', 'expiration_date' => '2021-01-01']],
            'preferred_role_ids' => [$this->jobTitle->id, $this->jobTitle->id],
            'project_types' => ['hourly', 'weekly'],
            'cv_file' => UploadedFile::fake()->create('cv.exe', 10, 'application/octet-stream'),
        ])->assertRedirect(route('my-profile.edit'))->assertSessionHasErrors([
            'linkedin_url', 'website', 'timezone', 'years_experience', 'hours_per_week', 'availability_status', 'preferred_work_mode',
            'skills.0.proficiency', 'skills.0.skill_id', 'skills.1.skill_id',
            'experiences.0.end_date', 'experiences.1.end_date', 'experiences.2.job_title',
            'educations.0.end_year', 'certifications.0.expiration_date',
            'preferred_role_ids.0', 'project_types.1', 'cv_file',
        ])->assertSessionHasInput('about', 'Keep me');

        $this->assertFalse(Profile::where('user_id', $this->employee->id)->exists(), 'nothing saved on failure');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_profile_page_renders_completion_and_saved_records(): void
    {
        $this->createAdminLayoutSchema();
        $this->completeEmployeeProfile($this->employee);
        Profile::where('user_id', $this->employee->id)->update(['about' => '<script>alert(1)</script>']);

        $response = $this->actingAs($this->employee)->get(route('my-profile.edit'));

        $response->assertOk()
            ->assertSee(route('my-profile.update'), false)
            ->assertSee('100<small>%</small>', false)
            ->assertSee(__('Ready for jobs and PODs'))
            ->assertSee('name="experiences[0][employer]" value="Acme"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('assets/custom/js/profile.js', false)
            ->assertSee('assets/custom/css/profile.css', false);

        // The editor no longer pulls its own jQuery/Chosen from a CDN (the layout's copies only).
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'chosen.jquery.min.js'));
        $this->assertSame(1, substr_count($html, 'ajax/libs/jquery/3.6.0/jquery.min.js'));
    }

    public function test_non_employees_and_guests_cannot_use_the_profile_editor(): void
    {
        foreach (['company', 'administrator'] as $type) {
            $account = $this->account($type);
            $this->actingAs($account)->get(route('my-profile.edit'))->assertForbidden();
            $this->actingAs($account)->post(route('my-profile.update'), ['about' => 'x'])->assertForbidden();
            $this->assertFalse(Profile::where('user_id', $account->id)->exists());
        }
        auth()->logout();

        $this->get(route('my-profile.edit'))->assertRedirect(route('login'));
        $this->post(route('my-profile.update'), ['about' => 'x'])->assertRedirect(route('login'));
    }

    public function test_cv_is_replaced_only_after_a_successful_save(): void
    {
        $this->save($this->employee, ['cv_file' => UploadedFile::fake()->create('first.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $first = Profile::where('user_id', $this->employee->id)->value('cv_file');
        Storage::disk('public')->assertExists($first);

        // A failing transaction keeps the old CV and removes the new upload.
        DB::statement("CREATE TRIGGER fail_experience BEFORE INSERT ON profile_experiences BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
        $this->save($this->employee, [
            'about' => 'Should roll back',
            'cv_file' => UploadedFile::fake()->create('second.pdf', 20, 'application/pdf'),
            'experiences' => [['employer' => 'Acme', 'job_title' => 'Dev']],
        ])->assertSessionHas('error');
        $this->assertSame($first, Profile::where('user_id', $this->employee->id)->value('cv_file'));
        $this->assertNull(Profile::where('user_id', $this->employee->id)->value('about'));
        $this->assertSame([$first], Storage::disk('public')->allFiles());
        DB::statement('DROP TRIGGER fail_experience');

        // A successful replacement deletes the previous file after commit.
        $this->save($this->employee, ['cv_file' => UploadedFile::fake()->create('third.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $third = Profile::where('user_id', $this->employee->id)->value('cv_file');
        $this->assertNotSame($first, $third);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($third);
    }

    // ================================================================ helpers

    private function save(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('my-profile.update'), ['sync' => self::SYNC_ALL] + $data);
    }

    private function rows(): array
    {
        return collect(['profiles', 'profile_experiences', 'profile_portfolios'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])->all();
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

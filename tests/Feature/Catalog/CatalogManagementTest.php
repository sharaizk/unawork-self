<?php

namespace Tests\Feature\Catalog;

use App\Models\Client;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Administrator catalog management for Industries, Skills and Professional Roles (job titles):
 * authorization, normalized case-insensitive names, search/pagination, safe deletion and the
 * skills API. In-memory SQLite only.
 */
class CatalogManagementTest extends TestCase
{
    use UsesInMemorySqlite;

    private User $admin;
    private User $employee;
    private User $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->createAdminLayoutSchema();
        Schema::table('workspaces', fn (Blueprint $t) => $t->unsignedBigInteger('industry_id')->nullable());
        Schema::create('pods_skills', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pod_id');
            $t->unsignedBigInteger('skill_id');
            $t->timestamps();
        });
        Schema::create('job_vacancy_skill', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('job_vacancy_id');
            $t->unsignedBigInteger('skill_id');
            $t->timestamps();
        });
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);

        $this->admin = $this->account('administrator');
        $this->employee = $this->account('employee');
        $this->company = $this->account('company');
    }

    /** route prefix, model, label column, route parameter */
    public static function catalogs(): array
    {
        return [
            'industries' => ['industries', Industry::class, 'name', 'industry'],
            'skills' => ['skills', Skill::class, 'name', 'skill'],
            'professional roles' => ['job-titles', JobTitle::class, 'title', 'job_title'],
        ];
    }

    public function test_administrator_sees_all_three_catalog_pages_and_the_sidebar_section(): void
    {
        foreach (self::catalogs() as [$route]) {
            $this->actingAs($this->admin)->get(route("$route.index"))->assertOk()
                ->assertSee(__('Catalog Management'))
                ->assertSee(route('industries.index'), false)
                ->assertSee(route('skills.index'), false)
                ->assertSee(route('job-titles.index'), false)
                ->assertSee(route("$route.create"), false);
        }
        $this->actingAs($this->admin)->get(route('job-titles.index'))->assertSee(__('Professional Roles'));
    }

    public function test_sidebar_catalog_links_are_administrator_only(): void
    {
        $this->completeEmployeeProfile($this->employee);
        $page = $this->actingAs($this->employee)->get(route('my-profile.edit'))->assertOk();

        $page->assertDontSee('data-sidebar-section="catalog"', false)
            ->assertDontSee(route('industries.index'), false)
            ->assertDontSee(route('skills.index'), false)
            ->assertDontSee(route('job-titles.index'), false);
    }

    #[DataProvider('catalogs')]
    public function test_non_administrators_cannot_manage_the_catalog(string $route, string $model, string $field, string $param): void
    {
        $entry = $this->entry($model, 'Existing');

        foreach ([$this->employee, $this->company] as $account) {
            $this->actingAs($account)->get(route("$route.index"))->assertForbidden();
            $this->actingAs($account)->get(route("$route.create"))->assertForbidden();
            $this->actingAs($account)->post(route("$route.store"), [$field => 'New'])->assertForbidden();
            $this->actingAs($account)->get(route("$route.edit", [$param => $entry]))->assertForbidden();
            $this->actingAs($account)->put(route("$route.update", [$param => $entry]), [$field => 'Renamed'])->assertForbidden();
            $this->actingAs($account)->delete(route("$route.destroy", [$param => $entry]))->assertForbidden();
        }
        auth()->logout();

        // A client session does not authenticate the web guard: sent to the web login.
        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client-' . $route . '@example.test', 'password' => 'x']);
        $this->actingAs($client, 'client');
        $this->app['auth']->shouldUse('web');
        $this->get(route("$route.index"))->assertRedirect(route('login'));
        $this->post(route("$route.store"), [$field => 'New'])->assertRedirect(route('login'));
        // Even resolved as the current user, a client is denied by the policy.
        $this->actingAs($client, 'client')->get(route("$route.index"))->assertForbidden();
        $this->actingAs($client, 'client')->post(route("$route.store"), [$field => 'New'])->assertForbidden();

        $this->assertSame(['Existing'], $model::pluck($field)->all());
    }

    #[DataProvider('catalogs')]
    public function test_administrator_creates_searches_renames_and_deletes_an_unused_entry(string $route, string $model, string $field, string $param): void
    {
        $this->actingAs($this->admin)->post(route("$route.store"), [$field => '  Cloud   Native  Platforms '])
            ->assertRedirect(route("$route.index"))->assertSessionHas('success');
        $entry = $model::sole();
        $this->assertSame('Cloud Native Platforms', $entry->getAttribute($field), 'whitespace normalized, capitalization kept');

        $this->entry($model, 'Accounting');
        $this->entry($model, 'Zoology');

        // Alphabetical listing with search (case-insensitive) and the modal/full-page forms.
        $this->actingAs($this->admin)->get(route("$route.index"))->assertOk()
            ->assertSeeInOrder(['Accounting', 'Cloud Native Platforms', 'Zoology']);
        $this->actingAs($this->admin)->get(route("$route.index", ['search' => 'cLoUd']))->assertOk()
            ->assertSee('Cloud Native Platforms')->assertDontSee('Zoology');
        $this->actingAs($this->admin)->get(route("$route.index", ['search' => 'nothing-matches']))->assertOk()
            ->assertSee(__('Try a different search term.'));
        $this->actingAs($this->admin)->get(route("$route.edit", [$param => $entry]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('value="Cloud Native Platforms"', false)->assertDontSee('<html', false);
        $this->actingAs($this->admin)->get(route("$route.create"))->assertOk()->assertSee('<html', false);

        $this->actingAs($this->admin)->put(route("$route.update", [$param => $entry]), [$field => 'Cloud-Native Platforms'])
            ->assertRedirect(route("$route.index"));
        $this->assertSame('Cloud-Native Platforms', $entry->fresh()->getAttribute($field));

        // Only the label is accepted from the browser.
        $this->actingAs($this->admin)->put(route("$route.update", [$param => $entry]), [$field => 'Cloud Platforms', 'id' => 999, 'created_at' => '2000-01-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame([$entry->id, 'Cloud Platforms'], [$entry->fresh()->id, $entry->fresh()->getAttribute($field)]);

        $this->actingAs($this->admin)->delete(route("$route.destroy", [$param => $entry]))
            ->assertRedirect(route("$route.index"))->assertSessionHas('success');
        $this->assertNull($entry->fresh());
    }

    #[DataProvider('catalogs')]
    public function test_names_are_unique_case_insensitively_and_never_blank(string $route, string $model, string $field, string $param): void
    {
        $existing = $this->entry($model, 'Data Science');
        $other = $this->entry($model, 'Design');

        $this->actingAs($this->admin)->from(route("$route.index"))->post(route("$route.store"), [$field => '  data   SCIENCE '])
            ->assertRedirect(route("$route.create"))->assertSessionHasErrors($field)->assertSessionHasInput($field);
        foreach (['', '    ', str_repeat('a', 256)] as $invalid) {
            $this->actingAs($this->admin)->post(route("$route.store"), [$field => $invalid])->assertSessionHasErrors($field);
        }
        $this->actingAs($this->admin)->put(route("$route.update", [$param => $other]), [$field => 'DATA science'])
            ->assertRedirect(route("$route.edit", [$param => $other]))->assertSessionHasErrors($field);

        // Renaming an entry to a different capitalization of itself is allowed.
        $this->actingAs($this->admin)->put(route("$route.update", [$param => $existing]), [$field => 'data science'])->assertSessionHasNoErrors();
        $this->assertSame(['Design', 'data science'], $model::query()->alphabetical()->pluck($field)->sort()->values()->all());
        $this->assertSame(2, $model::count());
    }

    public function test_index_paginates_25_entries_and_keeps_the_search_in_links(): void
    {
        foreach (range(1, 30) as $i) {
            $this->entry(Skill::class, sprintf('Skill %02d', $i));
        }
        $this->entry(Skill::class, 'Other');

        $first = $this->actingAs($this->admin)->get(route('skills.index', ['search' => 'skill']))->assertOk();
        $first->assertSee('Skill 25')->assertDontSee('Skill 26');
        $first->assertSee(e(route('skills.index', ['search' => 'skill', 'page' => 2])), false);
        $this->actingAs($this->admin)->get(route('skills.index', ['search' => 'skill', 'page' => 2]))->assertSee('Skill 30')->assertDontSee('Skill 01');
    }

    public function test_in_use_industries_cannot_be_deleted(): void
    {
        $industry = $this->entry(Industry::class, 'Banking');
        Workspace::whereKey($this->company->currant_workspace)->update(['industry_id' => $industry->id]);

        $this->assertInUse('industries', 'industry', $industry, 1, 'industry');
        $this->assertSame($industry->id, (int) Workspace::whereKey($this->company->currant_workspace)->value('industry_id'));
    }

    public function test_in_use_skills_cannot_be_deleted(): void
    {
        $skill = $this->entry(Skill::class, 'PHP');
        $this->employee->skills()->attach($skill->id, ['proficiency' => 'expert']);
        $podId = DB::table('pods')->insertGetId(['title' => 'Pod', 'created_by' => $this->company->id]);
        $vacancyId = DB::table('job_vacancies')->insertGetId(['user_id' => $this->company->id, 'title' => 'Vacancy']);
        DB::table('pods_skills')->insert(['pod_id' => $podId, 'skill_id' => $skill->id]);
        DB::table('job_vacancy_skill')->insert(['job_vacancy_id' => $vacancyId, 'skill_id' => $skill->id]);

        $this->assertInUse('skills', 'skill', $skill, 3, 'skill');
        $this->assertSame([1, 1, 1], [DB::table('user_skills')->count(), DB::table('pods_skills')->count(), DB::table('job_vacancy_skill')->count()]);
    }

    public function test_in_use_professional_roles_cannot_be_deleted(): void
    {
        $role = $this->entry(JobTitle::class, 'DevOps Engineer');
        $profile = new Profile();
        $profile->forceFill(['user_id' => $this->employee->id, 'job_title_id' => $role->id])->save();
        $profile->preferredRoles()->forceCreate(['profile_id' => $profile->id, 'job_title_id' => $role->id]);
        $profile->experiences()->forceCreate(['profile_id' => $profile->id, 'employer' => 'Acme', 'job_title' => 'DevOps', 'job_title_id' => $role->id]);

        $this->assertInUse('job-titles', 'job_title', $role, 3, 'professional role');
        $this->assertSame([$role->id, 1, 1], [(int) $profile->fresh()->job_title_id, DB::table('profile_preferred_roles')->count(), DB::table('profile_experiences')->whereNotNull('job_title_id')->count()]);
    }

    public function test_skills_api_reads_for_everyone_but_mutates_for_administrators_only(): void
    {
        $skill = $this->entry(Skill::class, 'Laravel');

        $this->actingAs($this->employee)->getJson('/api/skill')->assertOk()->assertJsonPath('0.name', 'Laravel');
        $this->actingAs($this->employee)->getJson("/api/skill/{$skill->id}")->assertOk();

        foreach ([$this->employee, $this->company] as $account) {
            $this->actingAs($account)->postJson(route('api.skill.store'), ['name' => 'Rust'])->assertForbidden();
            $this->actingAs($account)->putJson("/api/skill/{$skill->id}", ['name' => 'Renamed'])->assertForbidden();
            $this->actingAs($account)->deleteJson("/api/skill/{$skill->id}")->assertForbidden();
        }
        $this->assertSame(['Laravel'], Skill::pluck('name')->all());

        $this->actingAs($this->admin)->postJson(route('api.skill.store'), ['name' => ' Rust  Lang '])->assertCreated()->assertJsonPath('name', 'Rust Lang');
        $this->actingAs($this->admin)->postJson(route('api.skill.store'), ['name' => 'laravel'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($this->admin)->putJson("/api/skill/{$skill->id}", ['name' => 'Laravel 11'])->assertOk()->assertJsonPath('name', 'Laravel 11');

        $this->employee->skills()->attach($skill->id);
        $this->actingAs($this->admin)->deleteJson("/api/skill/{$skill->id}")->assertStatus(409);
        $this->actingAs($this->admin)->deleteJson("/api/skill/" . Skill::where('name', 'Rust Lang')->value('id'))->assertNoContent();

        // POST /api/skill is declared once.
        $posts = collect(app('router')->getRoutes())->filter(fn ($r) => $r->uri() === 'api/skill' && in_array('POST', $r->methods(), true));
        $this->assertCount(1, $posts);
        $this->assertSame('api.skill.store', $posts->first()->getName());
    }

    public function test_employees_select_existing_skills_and_roles_but_cannot_create_them_on_my_profile(): void
    {
        $skills = collect(['Go', 'SQL', 'Docker'])->map(fn ($n) => $this->entry(Skill::class, $n));
        $current = $this->entry(JobTitle::class, 'Backend Developer');
        $preferred = $this->entry(JobTitle::class, 'DevOps Engineer');

        $page = $this->actingAs($this->employee)->get(route('my-profile.edit'))->assertOk();
        $page->assertSee(__('Can\'t find your skill? Please contact an administrator.'))
            ->assertDontSee('profile-new-skill', false)
            ->assertDontSee(route('api.skill.store'), false)
            ->assertSee('>Docker</option>', false)
            ->assertSee('>DevOps Engineer</option>', false);

        $this->actingAs($this->employee)->post(route('my-profile.update'), [
            'sync' => ['skills', 'preferred_roles'],
            'job_title_id' => $current->id,
            'skills' => $skills->map(fn ($s) => ['skill_id' => $s->id, 'proficiency' => 'advanced'])->all(),
            'preferred_role_ids' => [$preferred->id, $current->id],
        ])->assertSessionHasNoErrors();

        $profile = Profile::where('user_id', $this->employee->id)->sole();
        $this->assertSame($current->id, $profile->job_title_id);
        $this->assertEqualsCanonicalizing([$preferred->id, $current->id], $profile->preferredRoles()->pluck('job_title_id')->all());
        $this->assertSame(['advanced', 'advanced', 'advanced'], $this->employee->fresh()->skills->pluck('pivot.proficiency')->all());
        $this->assertSame(3, Skill::count(), 'no skills created by the employee');
    }

    // ================================================================ helpers

    private function assertInUse(string $route, string $param, Model $entry, int $count, string $noun): void
    {
        $message = __("This {$noun} cannot be deleted because it is currently used by :count records.", ['count' => $count]);
        $this->assertSame($count, $entry->usageCount());

        $this->actingAs($this->admin)->from(route("$route.index"))->delete(route("$route.destroy", [$param => $entry]))
            ->assertRedirect(route("$route.index"))->assertSessionHas('error', $message);
        $this->actingAs($this->admin)->deleteJson(route("$route.destroy", [$param => $entry]))
            ->assertStatus(409)->assertJson(['message' => $message, 'usage' => $count]);
        $this->assertNotNull($entry->fresh());

        // The index shows the usage and offers no delete confirmation for the entry.
        $this->actingAs($this->admin)->get(route("$route.index"))->assertOk()
            ->assertDontSee('data-confirm-yes="delete-form-' . $route . '-' . $entry->getKey() . '"', false);
    }

    private function entry(string $model, string $label): Model
    {
        $entry = new $model();
        $entry->setAttribute($model::catalogColumn(), $label)->save();

        return $entry;
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

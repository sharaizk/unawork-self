<?php

namespace Tests\Feature\Catalog;

use App\Filters\Employee\EmployeeFilter;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Skills and Professional Roles tagged with Industries; an employee's primary industry narrows the
 * /my-profile pickers and is searchable. In-memory SQLite only.
 */
class IndustryRelationsTest extends TestCase
{
    use UsesInMemorySqlite;

    private User $admin;
    private User $employee;
    private Industry $tech;
    private Industry $health;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->createAdminLayoutSchema();
        foreach (['job_vacancy_skill' => 'job_vacancy_id'] as $table => $key) {
            Schema::create($table, function (Blueprint $t) use ($key) {
                $t->id();
                $t->unsignedBigInteger($key);
                $t->unsignedBigInteger('skill_id');
                $t->timestamps();
            });
        }
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);

        $this->admin = $this->account('administrator');
        $this->employee = $this->account('employee');
        $this->tech = Industry::create(['name' => 'Technology']);
        $this->health = Industry::create(['name' => 'Healthcare']);
    }

    public function test_administrator_tags_skills_and_roles_with_industries(): void
    {
        $this->actingAs($this->admin)->post(route('skills.store'), [
            'name' => 'Kubernetes', 'industry_ids_present' => 1, 'industry_ids' => [$this->tech->id, $this->health->id],
        ])->assertSessionHasNoErrors();
        $skill = Skill::where('name', 'Kubernetes')->sole();
        $this->assertEqualsCanonicalizing([$this->tech->id, $this->health->id], $skill->industries->pluck('id')->all());

        $this->actingAs($this->admin)->post(route('job-titles.store'), [
            'title' => 'Nurse Practitioner', 'industry_ids_present' => 1, 'industry_ids' => [$this->health->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$this->health->id], JobTitle::where('title', 'Nurse Practitioner')->sole()->industries->pluck('id')->all());

        // The edit form shows the current tags; unticking everything clears them.
        $this->actingAs($this->admin)->get(route('skills.edit', $skill), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('name="industry_ids[]" value="' . $this->tech->id . '"', false)
            ->assertSee('id="catalog-industry-' . $this->tech->id . '" checked', false);
        $this->actingAs($this->admin)->put(route('skills.update', $skill), ['name' => 'Kubernetes', 'industry_ids_present' => 1])->assertSessionHasNoErrors();
        $this->assertCount(0, $skill->fresh()->industries);

        // Invalid / duplicate industries are field errors; industries themselves are never tagged.
        $this->actingAs($this->admin)->post(route('skills.store'), ['name' => 'X', 'industry_ids_present' => 1, 'industry_ids' => [999]])
            ->assertSessionHasErrors('industry_ids.0');
        $this->actingAs($this->admin)->get(route('industries.create'))->assertOk()->assertDontSee('industry_ids[]', false);

        // API: tags are optional; omitting them keeps the existing ones.
        $id = $this->actingAs($this->admin)->postJson(route('api.skill.store'), ['name' => 'Telehealth', 'industry_ids' => [$this->health->id]])->assertCreated()->json('id');
        $this->actingAs($this->admin)->putJson("/api/skill/{$id}", ['name' => 'Telemedicine'])->assertOk();
        $this->assertSame([$this->health->id], Skill::find($id)->industries->pluck('id')->all());
    }

    public function test_index_shows_and_filters_by_industry(): void
    {
        $k8s = $this->skill('Kubernetes', [$this->tech]);
        $this->skill('Triage', [$this->health]);
        $this->skill('Communication', []);

        $this->actingAs($this->admin)->get(route('skills.index'))->assertOk()
            ->assertSeeInOrder(['Communication', __('All industries'), 'Kubernetes', 'Technology', 'Triage', 'Healthcare']);
        $this->actingAs($this->admin)->get(route('skills.index', ['industry' => $this->tech->id]))->assertOk()
            ->assertSee('Kubernetes')->assertDontSee('>Triage<', false)->assertDontSee('>Communication<', false);

        // The API narrows to the industry plus general skills.
        $names = collect($this->actingAs($this->employee)->getJson('/api/skill?industry=' . $this->tech->id)->assertOk()->json())->pluck('name')->all();
        $this->assertSame(['Communication', 'Kubernetes'], $names);
        $this->assertSame(['Communication', 'Kubernetes'], Skill::query()->forIndustry($this->tech->id)->alphabetical()->pluck('name')->all());
        $this->assertSame($k8s->id, Skill::query()->forIndustry($this->tech->id)->where('name', 'Kubernetes')->value('id'));
    }

    public function test_industry_links_are_shown_separately_and_never_count_as_usage(): void
    {
        $skill = $this->skill('Kubernetes', [$this->tech]);
        $role = JobTitle::create(['title' => 'SRE']);
        $role->industries()->attach($this->tech->id);

        // Freshly seeded-style data: linked skills/roles, but nobody uses the industry yet.
        $this->assertSame(0, $this->tech->usageCount());
        $this->actingAs($this->admin)->get(route('industries.index'))->assertOk()
            ->assertSee(__('Linked to'))
            ->assertSee('href="' . e(route('skills.index', ['industry' => $this->tech->id])) . '" class="text-muted">1 skills</a>', false)
            ->assertSee(__('Its links to skills and roles are removed too; the skills and roles themselves are kept. This action can not be undone.'));

        // A profile is real usage and blocks deletion.
        $profile = new Profile();
        $profile->forceFill(['user_id' => $this->employee->id, 'industry_id' => $this->tech->id])->save();
        $this->assertSame(1, $this->tech->fresh()->usageCount());
        $this->actingAs($this->admin)->delete(route('industries.destroy', $this->tech))
            ->assertSessionHas('error', __('This industry cannot be deleted because it is currently used by :count records.', ['count' => 1]));
        $this->assertSame([1, 1], [DB::table('industry_skill')->count(), DB::table('industry_job_title')->count()]);

        // Once unused, deleting it removes only the links; the skill and role stay.
        $profile->forceFill(['industry_id' => null])->save();
        $this->actingAs($this->admin)->delete(route('industries.destroy', $this->tech))->assertSessionHas('success');
        $this->assertNull($this->tech->fresh());
        $this->assertSame([0, 0], [DB::table('industry_skill')->count(), DB::table('industry_job_title')->count()]);
        $this->assertNotNull($skill->fresh());
        $this->assertNotNull($role->fresh());
    }

    public function test_a_linked_skill_or_role_is_deletable_and_takes_only_its_links(): void
    {
        $skill = $this->skill('Kubernetes', [$this->tech]);
        $role = JobTitle::create(['title' => 'SRE']);
        $role->industries()->attach($this->tech->id);

        $this->assertSame(0, $skill->usageCount());
        $this->actingAs($this->admin)->delete(route('skills.destroy', $skill))->assertSessionHas('success');
        $this->actingAs($this->admin)->delete(route('job-titles.destroy', $role))->assertSessionHas('success');
        $this->assertSame([0, 0], [DB::table('industry_skill')->count(), DB::table('industry_job_title')->count()]);
        $this->assertNotNull($this->tech->fresh());
    }

    public function test_my_profile_preloads_industry_options_and_saves_the_industry(): void
    {
        $k8s = $this->skill('Kubernetes', [$this->tech]);
        $triage = $this->skill('Triage', [$this->health]);
        $general = $this->skill('Communication', []);
        $nurse = JobTitle::create(['title' => 'Nurse']);
        $nurse->industries()->attach($this->health->id);

        $page = $this->actingAs($this->employee)->get(route('my-profile.edit'))->assertOk();
        $page->assertSee('id="industry_id"', false)
            ->assertSee('<option value="' . $this->health->id . '" >Healthcare</option>', false)
            ->assertSee('value="' . $k8s->id . '" data-industries="' . $this->tech->id . '"', false)
            ->assertSee('value="' . $general->id . '" data-industries=""', false)
            ->assertSee('value="' . $nurse->id . '" data-industries="' . $this->health->id . '"', false)
            ->assertSee('id="profile-show-all-catalog"', false);

        // The industry narrows the pickers only: a skill from another industry still saves.
        $this->actingAs($this->employee)->post(route('my-profile.update'), [
            'sync' => ['skills', 'preferred_roles'],
            'industry_id' => $this->health->id,
            'skills' => [['skill_id' => $triage->id, 'proficiency' => 'expert'], ['skill_id' => $k8s->id, 'proficiency' => 'beginner']],
            'preferred_role_ids' => [$nurse->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->health->id, Profile::where('user_id', $this->employee->id)->value('industry_id'));
        $this->assertCount(2, $this->employee->fresh()->skills);

        $page = $this->actingAs($this->employee)->get(route('my-profile.edit'))
            ->assertSee('<option value="' . $this->health->id . '" selected>Healthcare</option>', false);

        // Preferred roles render as chips (hidden inputs) and the chosen role leaves the list.
        $html = $page->getContent();
        $page->assertDontSee('name="preferred_role_ids[]" multiple', false)
            ->assertSee('data-role-chip="' . $nurse->id . '"', false)
            ->assertSee('<input type="hidden" name="preferred_role_ids[]" value="' . $nurse->id . '">', false)
            ->assertSee('aria-label="' . e(__('Remove :role', ['role' => 'Nurse'])) . '"', false);
        $this->assertMatchesRegularExpression('/data-role-option="' . $nurse->id . '"\s+data-industries="' . $this->health->id . '"[^>]*\shidden\s*>/', $html);
        $page->assertSee('data-i18n=', false)->assertSee(e(__('Change industry?')), false);

        $this->actingAs($this->employee)->post(route('my-profile.update'), ['industry_id' => 999])->assertSessionHasErrors('industry_id');
    }

    public function test_employee_search_filters_by_industry(): void
    {
        $other = $this->account('employee');
        foreach ([[$this->employee, $this->tech], [$other, $this->health]] as [$user, $industry]) {
            (new Profile())->forceFill(['user_id' => $user->id, 'industry_id' => $industry->id])->save();
        }

        $ids = (new EmployeeFilter(['industry_id' => $this->tech->id]))->applyFilters(User::employees())->pluck('users.id')->all();
        $this->assertSame([$this->employee->id], $ids);

        $this->actingAs($this->admin)->getJson('/api/employees?industry_id=' . $this->health->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id)->assertJsonPath('data.0.industry', 'Healthcare');
        $this->actingAs($this->admin)->getJson('/api/employees?industry_id=999')->assertStatus(422);
    }

    public function test_relation_migration_rolls_back_cleanly(): void
    {
        $migration = require database_path('migrations/2026_11_18_000100_add_industry_relations_to_catalogs.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('industry_skill'));
        $this->assertFalse(Schema::hasTable('industry_job_title'));
        $this->assertFalse(Schema::hasColumn('profiles', 'industry_id'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('profiles', 'industry_id'));
        $this->assertTrue(Schema::hasIndex('industry_skill', 'industry_skill_industry_id_skill_id_unique', 'unique'));
    }

    // ================================================================ helpers

    private function skill(string $name, array $industries): Skill
    {
        $skill = Skill::create(['name' => $name]);
        $skill->industries()->attach(collect($industries)->pluck('id')->all());

        return $skill;
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

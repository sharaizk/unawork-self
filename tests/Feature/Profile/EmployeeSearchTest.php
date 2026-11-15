<?php

namespace Tests\Feature\Profile;

use App\Filters\Employee\EmployeeFilter;
use App\Models\JobTitle;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-007: structured employee search (EmployeeFilter / FilterRequest / EmployeeService /
 * EmployeeResource), alongside the existing filters. In-memory SQLite only.
 */
class EmployeeSearchTest extends TestCase
{
    use UsesInMemorySqlite;

    private array $e = [];
    private JobTitle $developer;
    private JobTitle $designer;
    private Skill $php;
    private Skill $figma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);

        $this->developer = JobTitle::create(['title' => 'Developer']);
        $this->designer = JobTitle::create(['title' => 'Designer']);
        $this->php = Skill::create(['name' => 'PHP']);
        $this->figma = Skill::create(['name' => 'Figma']);

        $this->e['ana'] = $this->employee('Ana', [
            'job_title_id' => $this->developer->id, 'years_experience' => 8, 'country' => 'Sri Lanka', 'city' => 'Colombo',
            'timezone' => 'Asia/Colombo', 'availability_status' => 'immediately', 'preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract',
        ], [$this->php->id => 'expert'], [$this->developer->id], ['long_term', 'hourly']);
        $this->e['ben'] = $this->employee('Ben', [
            'job_title_id' => $this->designer->id, 'years_experience' => 2.5, 'country' => 'Germany', 'city' => 'Berlin',
            'timezone' => 'Europe/Berlin', 'availability_status' => 'within_one_month', 'preferred_work_mode' => 'onsite', 'preferred_engagement_type' => 'full_time',
        ], [$this->figma->id => 'intermediate', $this->php->id => 'beginner'], [$this->designer->id], ['short_term']);
        // A legacy employee: profile without structured data, a skill without proficiency.
        $this->e['cy'] = $this->employee('Cy', ['country' => 'Sri Lanka', 'city' => 'Kandy'], [$this->php->id => null], [], []);
        // A company never appears in employee search.
        User::factory()->company()->create(['name' => 'Company']);
    }

    public function test_structured_filters(): void
    {
        $cases = [
            'skill ids' => [['skill_ids' => [$this->figma->id]], ['ben']],
            'skill proficiency' => [['skill_proficiency' => 'expert'], ['ana']],
            'skill ids + proficiency' => [['skill_ids' => [$this->php->id], 'skill_proficiency' => ['beginner', 'intermediate']], ['ben']],
            'min years' => [['min_years_experience' => 3], ['ana']],
            'max years' => [['max_years_experience' => 3], ['ben']],
            'years range' => [['min_years_experience' => 2, 'max_years_experience' => 10], ['ana', 'ben']],
            'current job title' => [['job_title_id' => $this->designer->id], ['ben']],
            'preferred roles' => [['preferred_role_ids' => [$this->developer->id]], ['ana']],
            'country' => [['country' => 'Sri Lanka'], ['ana', 'cy']],
            'city' => [['city' => 'Berlin'], ['ben']],
            'timezone' => [['timezone' => 'Asia/Colombo'], ['ana']],
            'availability' => [['availability_status' => ['within_one_month', 'within_two_weeks']], ['ben']],
            'project type' => [['project_types' => 'hourly'], ['ana']],
            'work mode' => [['preferred_work_mode' => 'onsite'], ['ben']],
            'engagement type' => [['preferred_engagement_type' => ['contract']], ['ana']],
            'combined' => [['country' => 'Sri Lanka', 'preferred_work_mode' => 'remote', 'skill_ids' => [$this->php->id]], ['ana']],
            'no filters' => [[], ['ana', 'ben', 'cy']],
        ];

        foreach ($cases as $label => [$filters, $expected]) {
            // Through the joined listing query (API / hiring index)...
            $this->assertEqualsCanonicalizing($this->ids($expected), app(EmployeeService::class)->getEmployees($filters)->pluck('id')->all(), "service: $label");
            // ...and on a plain employees() query (hiring search endpoint).
            $plain = (new EmployeeFilter($filters))->applyFilters(User::employees())->pluck('users.id')->all();
            $this->assertEqualsCanonicalizing($this->ids($expected), $plain, "plain: $label");
        }
    }

    public function test_api_validates_filters_and_returns_the_structured_fields(): void
    {
        $viewer = User::factory()->company()->create();

        $response = $this->actingAs($viewer)->getJson('/api/employees?' . http_build_query([
            'preferred_work_mode' => 'remote', 'project_types' => ['hourly'], 'min_years_experience' => 5,
        ]))->assertOk();
        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->e['ana']->id)
            ->assertJsonPath('data.0.job_title', 'Developer')
            ->assertJsonPath('data.0.years_experience', 8)
            ->assertJsonPath('data.0.timezone', 'Asia/Colombo')
            ->assertJsonPath('data.0.availability_status', 'immediately')
            ->assertJsonPath('data.0.preferred_engagement_type', 'contract')
            ->assertJsonPath('data.0.skills.0.proficiency', 'expert')
            ->assertJsonPath('data.0.preferred_roles.0.title', 'Developer');
        $this->assertEqualsCanonicalizing(['long_term', 'hourly'], $response->json('data.0.project_types'));

        // Legacy consumers: the new optional fields are simply null / empty.
        $legacy = $this->actingAs($viewer)->getJson('/api/employees?city=Kandy')->assertOk()->json('data.0');
        $this->assertSame([null, null, null, null, [], []], [$legacy['years_experience'], $legacy['timezone'], $legacy['availability_status'], $legacy['skills'][0]['proficiency'], $legacy['preferred_roles'], $legacy['project_types']]);
        $this->assertSame('Kandy', $legacy['city']);

        $this->actingAs($viewer)->getJson('/api/employees?preferred_work_mode=space')->assertStatus(422)->assertJsonValidationErrors('preferred_work_mode');
        $this->actingAs($viewer)->getJson('/api/employees?timezone=Mars/Olympus')->assertStatus(422)->assertJsonValidationErrors('timezone');
        $this->actingAs($viewer)->getJson('/api/employees?min_years_experience=abc')->assertStatus(422);
    }

    public function test_listing_does_not_issue_queries_per_employee(): void
    {
        $viewer = User::factory()->company()->create();
        $count = function () use ($viewer) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($viewer)->getJson('/api/employees')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $baseline = $count();
        foreach (range(1, 4) as $i) {
            $this->employee("Extra{$i}", ['country' => 'X'], [$this->php->id => 'advanced'], [$this->developer->id], ['ongoing']);
        }
        $this->assertSame($baseline, $count());
    }

    // ================================================================ helpers

    private function ids(array $keys): array
    {
        return array_map(fn ($k) => $this->e[$k]->id, $keys);
    }

    private function employee(string $name, array $profile, array $skills, array $roles, array $projectTypes): User
    {
        $user = User::factory()->employee()->create(['name' => $name]);
        $record = new Profile();
        $record->forceFill(['user_id' => $user->id] + $profile)->save();
        foreach ($skills as $skillId => $proficiency) {
            $user->skills()->attach($skillId, ['proficiency' => $proficiency]);
        }
        foreach ($roles as $roleId) {
            $record->preferredRoles()->forceCreate(['profile_id' => $record->id, 'job_title_id' => $roleId]);
        }
        foreach ($projectTypes as $type) {
            $record->projectTypes()->forceCreate(['profile_id' => $record->id, 'project_type' => $type]);
        }

        return $user;
    }
}

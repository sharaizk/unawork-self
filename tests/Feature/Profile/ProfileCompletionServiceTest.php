<?php

namespace Tests\Feature\Profile;

use App\Models\Client;
use App\Models\JobTitle;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\Profile\ProfileCompletionService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-007: ProfileCompletionService is the only scoring implementation - ten sections of 10%,
 * all-or-nothing, with the missing requirements listed. In-memory SQLite only.
 */
class ProfileCompletionServiceTest extends TestCase
{
    use UsesInMemorySqlite;

    private const ALL = ['summary', 'headline', 'location', 'availability', 'skills', 'experience', 'education', 'preferred_roles', 'work_preferences', 'links'];

    private ProfileCompletionService $completion;
    private JobTitle $jobTitle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        $this->completion = app(ProfileCompletionService::class);
        $this->jobTitle = JobTitle::create(['title' => 'Designer']);
    }

    public function test_an_employee_without_a_profile_scores_zero_with_every_requirement_listed(): void
    {
        $employee = User::factory()->employee()->create();

        $breakdown = $this->completion->breakdown($employee);

        $this->assertSame(0, $this->completion->percentage($employee));
        $this->assertSame(0, $breakdown['percentage']);
        $this->assertSame(80, $breakdown['required']);
        $this->assertFalse($breakdown['eligible']);
        $this->assertSame(self::ALL, array_keys($breakdown['sections']));
        foreach ($breakdown['sections'] as $key => $section) {
            $this->assertFalse($section['complete'], $key);
            $this->assertSame(10, $section['weight']);
            $this->assertNotEmpty($section['missing'], $key);
        }
        $this->assertCount(3, $breakdown['sections']['location']['missing']);
        $this->assertFalse($this->completion->isEligible($employee));
        $this->assertTrue($this->completion->requiresOnboarding($employee));
    }

    public function test_partial_profile_scores_only_complete_sections(): void
    {
        $employee = $this->employeeWith(['summary', 'location']);
        // A half-finished section scores nothing and lists exactly what is missing.
        $employee->profile->forceFill(['job_title_id' => $this->jobTitle->id])->save();

        $breakdown = $this->completion->breakdown($employee->fresh());

        $this->assertSame(20, $breakdown['percentage']);
        $this->assertTrue($breakdown['sections']['summary']['complete']);
        $this->assertTrue($breakdown['sections']['location']['complete']);
        $this->assertFalse($breakdown['sections']['headline']['complete']);
        $this->assertSame([__('Enter your total years of experience')], $breakdown['sections']['headline']['missing']);
    }

    public function test_seventy_percent_is_not_eligible(): void
    {
        $employee = $this->employeeWith(array_slice(self::ALL, 0, 7));

        $this->assertSame(70, $this->completion->percentage($employee));
        $this->assertFalse($this->completion->isEligible($employee));
        $this->assertSame(['preferred_roles', 'work_preferences', 'links'], array_keys(array_filter(
            $this->completion->breakdown($employee)['sections'], fn ($s) => !$s['complete']
        )));
    }

    public function test_eighty_percent_is_eligible(): void
    {
        $employee = $this->employeeWith(array_slice(self::ALL, 0, 8));

        $this->assertSame(80, $this->completion->percentage($employee));
        $this->assertTrue($this->completion->isEligible($employee));
        $this->assertFalse($this->completion->requiresOnboarding($employee));
        $this->assertTrue($this->completion->breakdown($employee)['eligible']);
    }

    public function test_a_fully_complete_profile_scores_one_hundred(): void
    {
        $employee = $this->employeeWith(self::ALL);

        $breakdown = $this->completion->breakdown($employee);
        $this->assertSame(100, $breakdown['percentage']);
        foreach ($breakdown['sections'] as $section) {
            $this->assertSame([], $section['missing']);
        }

        // The trait's fixture helper produces the same result.
        $other = User::factory()->employee()->create();
        $this->completeEmployeeProfile($other);
        $this->assertSame(100, $this->completion->percentage($other->fresh()));
    }

    public function test_rules_for_individual_sections(): void
    {
        $employee = $this->employeeWith(['availability', 'skills', 'education', 'links', 'location']);
        $profile = $employee->profile;
        $sections = fn () => $this->completion->breakdown($employee->fresh())['sections'];

        // Availability: a specific date requires available_from.
        $profile->forceFill(['availability_status' => 'specific_date', 'available_from' => null])->save();
        $this->assertSame([__('Enter the date you are available from')], $sections()['availability']['missing']);
        $profile->forceFill(['available_from' => '2027-01-10'])->save();
        $this->assertTrue($sections()['availability']['complete']);

        // Skills: legacy rows without proficiency do not count.
        DB::table('user_skills')->where('user_id', $employee->id)->limit(1)->update(['proficiency' => null]);
        $this->assertFalse($sections()['skills']['complete']);

        // Education OR certification.
        $profile->educations()->delete();
        $this->assertFalse($sections()['education']['complete']);
        $profile->certifications()->forceCreate(['profile_id' => $profile->id, 'name' => 'AWS']);
        $this->assertTrue($sections()['education']['complete']);

        // Links: any one of LinkedIn, GitHub, website or a portfolio record.
        $profile->forceFill(['linkedin_url' => null])->save();
        $this->assertFalse($sections()['links']['complete']);
        $profile->portfolios()->forceCreate(['profile_id' => $profile->id, 'title' => 'Site', 'url' => 'https://example.test']);
        $this->assertTrue($sections()['links']['complete']);

        // Location: an invalid timezone does not count.
        $profile->forceFill(['timezone' => 'Mars/Olympus'])->save();
        $this->assertSame([__('Select a valid timezone')], $sections()['location']['missing']);
    }

    public function test_legacy_profile_data_scores_without_errors(): void
    {
        $employee = User::factory()->employee()->create();
        $skill = Skill::create(['name' => 'PHP']);
        DB::table('user_skills')->insert(['user_id' => $employee->id, 'skill_id' => $skill->id]);
        Profile::forceCreate(['user_id' => $employee->id, 'country' => 'Sri Lanka', 'city' => 'Kandy', 'zip_code' => '20000',
            'address' => '1 Road', 'phone_number' => '123', 'cv_file' => 'profile-files/old.pdf', 'about' => 'Legacy bio',
            'website' => 'https://legacy.example.test', 'job_title_id' => $this->jobTitle->id]);

        $breakdown = $this->completion->breakdown($employee);

        $this->assertSame(20, $breakdown['percentage']); // summary + links (website)
        $this->assertFalse($breakdown['sections']['skills']['complete']);
        $this->assertFalse($breakdown['sections']['location']['complete'], 'no timezone yet');
    }

    public function test_non_employees_are_never_subject_to_the_minimum(): void
    {
        $company = User::factory()->company()->create();
        $admin = User::factory()->administrator()->create();
        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'x']);

        foreach ([$company, $admin] as $account) {
            $this->assertTrue($this->completion->isEligible($account));
            $this->assertFalse($this->completion->requiresOnboarding($account));
            $this->assertFalse($this->completion->breakdown($account)['subject']);
            $this->assertTrue($this->completion->breakdown($account)['eligible']);
        }
        $this->assertTrue($this->completion->isEligible($client));
        $this->assertFalse($this->completion->requiresOnboarding($client));
        $this->assertFalse($this->completion->requiresOnboarding(null));
    }

    public function test_the_minimum_comes_from_config(): void
    {
        $employee = $this->employeeWith(array_slice(self::ALL, 0, 7));
        config(['profile.minimum_completion' => 70]);

        $this->assertSame(70, $this->completion->required());
        $this->assertTrue($this->completion->isEligible($employee));
    }

    // ================================================================ helpers

    /** An employee whose profile satisfies exactly the given sections. */
    private function employeeWith(array $sections): User
    {
        $employee = User::factory()->employee()->create();
        $profile = new Profile();
        $profile->forceFill(['user_id' => $employee->id]);
        $has = fn (string $s) => in_array($s, $sections, true);

        $profile->forceFill(array_filter([
            'about' => $has('summary') ? 'Summary' : null,
            'job_title_id' => $has('headline') ? $this->jobTitle->id : null,
            'years_experience' => $has('headline') ? 4.5 : null,
            'country' => $has('location') ? 'Sri Lanka' : null,
            'city' => $has('location') ? 'Colombo' : null,
            'timezone' => $has('location') ? 'Asia/Colombo' : null,
            'availability_status' => $has('availability') ? 'immediately' : null,
            'hours_per_week' => $has('availability') ? 20 : null,
            'preferred_work_mode' => $has('work_preferences') ? 'hybrid' : null,
            'preferred_engagement_type' => $has('work_preferences') ? 'part_time' : null,
            'linkedin_url' => $has('links') ? 'https://www.linkedin.com/in/x' : null,
        ], fn ($v) => $v !== null))->save();

        if ($has('skills')) {
            foreach (['A', 'B', 'C'] as $name) {
                $employee->skills()->attach(Skill::firstOrCreate(['name' => $name])->id, ['proficiency' => 'expert']);
            }
        }
        if ($has('experience')) {
            $profile->experiences()->forceCreate(['profile_id' => $profile->id, 'employer' => 'Acme', 'job_title' => 'Designer']);
        }
        if ($has('education')) {
            $profile->educations()->forceCreate(['profile_id' => $profile->id, 'institution' => 'Uni']);
        }
        if ($has('preferred_roles')) {
            $profile->preferredRoles()->forceCreate(['profile_id' => $profile->id, 'job_title_id' => $this->jobTitle->id]);
        }
        if ($has('work_preferences')) {
            $profile->projectTypes()->forceCreate(['profile_id' => $profile->id, 'project_type' => 'hourly']);
        }

        return $employee->fresh();
    }
}

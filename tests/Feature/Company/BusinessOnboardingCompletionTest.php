<?php

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Pod;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Models\WorkspaceWorkerRequirement;
use App\Services\Company\BusinessOnboardingCompletionService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * UNA-010: BusinessOnboardingCompletionService is the only scoring implementation - eight weighted
 * sections, all-or-nothing, calculated from the company workspace. In-memory SQLite only.
 */
class BusinessOnboardingCompletionTest extends TestCase
{
    use UsesInMemorySqlite;

    private const WEIGHTS = ['company' => 15, 'industry' => 10, 'contact' => 15, 'online' => 10, 'description' => 10, 'requirements' => 20, 'matching' => 10, 'approvals' => 10];

    private BusinessOnboardingCompletionService $completion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        $this->completion = app(BusinessOnboardingCompletionService::class);
    }

    public function test_an_empty_company_workspace_scores_zero_with_every_item_missing(): void
    {
        $company = $this->company();
        $workspace = $this->completion->companyWorkspace($company);

        $breakdown = $this->completion->breakdown($workspace);

        $this->assertSame(0, $this->completion->percentage($workspace));
        $this->assertSame([0, 100, false], [$breakdown['percentage'], $breakdown['required'], $breakdown['complete']]);
        $this->assertSame(array_keys(self::WEIGHTS), array_keys($breakdown['sections']));
        $this->assertSame(self::WEIGHTS, array_map(fn ($s) => $s['weight'], $breakdown['sections']));
        $this->assertSame(100, array_sum(config('company_onboarding.weights')));
        $this->assertCount(2 + 1 + 5 + 2 + 1 + 1 + 1 + 2, $this->completion->missingRequirements($workspace));
        $this->assertTrue($this->completion->requiresOnboarding($company));
    }

    #[DataProvider('sections')]
    public function test_each_section_contributes_its_documented_weight(string $section): void
    {
        $company = $this->company();
        $workspace = $this->completion->companyWorkspace($company);
        $this->fillSection($workspace, $section);

        $breakdown = $this->completion->breakdown($workspace->fresh());

        $this->assertSame(self::WEIGHTS[$section], $breakdown['percentage'], $section);
        $this->assertTrue($breakdown['sections'][$section]['complete']);
        $this->assertSame([], $breakdown['sections'][$section]['missing']);
    }

    public static function sections(): array
    {
        return array_combine(array_keys(self::WEIGHTS), array_map(fn ($s) => [$s], array_keys(self::WEIGHTS)));
    }

    public function test_a_fully_complete_company_scores_100_and_removing_any_mandatory_value_drops_it(): void
    {
        $company = $this->company();
        $workspace = $this->completeBusinessProfile($company);
        $this->assertSame(100, $this->completion->percentage($workspace));
        $this->assertTrue($this->completion->isComplete($workspace));
        $this->assertFalse($this->completion->requiresOnboarding($company));
        $this->assertSame([], $this->completion->missingRequirements($workspace));

        $removals = [
            'company' => ['logo' => null], 'industry' => ['industry_id' => null], 'contact' => ['contact_email' => 'not-an-email'],
            'online' => ['linkedin' => null, 'facebook' => 'ftp://nope'], 'description' => ['description' => 'Too short'],
            'matching' => ['matching_mode' => null], 'approvals' => ['default_inner_circle_approval' => null],
        ];
        foreach ($removals as $section => $values) {
            $original = $workspace->only(array_keys($values));
            Workspace::whereKey($workspace->id)->update($values);
            $fresh = $workspace->fresh();
            $this->assertSame(100 - self::WEIGHTS[$section], $this->completion->percentage($fresh), $section);
            $this->assertFalse($this->completion->isComplete($fresh));
            Workspace::whereKey($workspace->id)->update(array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $original));
        }

        // Requirements: inactive or incomplete requirements do not count.
        WorkspaceWorkerRequirement::query()->update(['is_active' => false]);
        $this->assertSame(80, $this->completion->percentage($workspace->fresh()));
        WorkspaceWorkerRequirement::query()->update(['is_active' => true, 'preferred_work_mode' => null]);
        $this->assertSame(80, $this->completion->percentage($workspace->fresh()));

        // The stored completed_at never decides current eligibility.
        $workspace->forceFill(['business_onboarding_completed_at' => now()])->save();
        $this->assertFalse($this->completion->isComplete($workspace->fresh()));
    }

    public function test_existing_workspace_company_fields_are_reused(): void
    {
        $company = $this->company();
        $workspace = $this->completion->companyWorkspace($company);
        // Values a company saved through the legacy settings form before UNA-010.
        DB::table('workspaces')->where('id', $workspace->id)->update([
            'company' => 'Legacy Co', 'logo' => $workspace->id . '_logo-light.png', 'industry_id' => Industry::create(['name' => 'Banking'])->id,
            'telephone' => '123', 'city' => 'Kandy', 'country' => 'Sri Lanka', 'website' => 'https://legacy.example.test',
            'facebook' => 'https://facebook.com/legacy', 'description' => str_repeat('x', 100),
        ]);

        $sections = $this->completion->breakdown($workspace->fresh())['sections'];

        foreach (['company', 'industry', 'online', 'description'] as $section) {
            $this->assertTrue($sections[$section]['complete'], $section);
        }
        $this->assertSame([__('Enter a contact name'), __('Enter a valid contact email')], $sections['contact']['missing']);
        $this->assertSame(45, $this->completion->percentage($workspace->fresh()));
    }

    public function test_only_the_companys_own_non_pod_workspace_counts_and_other_accounts_are_exempt(): void
    {
        $company = $this->company();
        $own = $this->completeBusinessProfile($company);
        // Inside a POD workspace: currant_workspace must not be used to find the company profile.
        $podWorkspace = Workspace::create(['name' => 'Pod WS', 'created_by' => $company->id, 'lang' => 'en']);
        $pod = Pod::forceCreate(['title' => 'Pod', 'created_by' => $company->id]);
        $podWorkspace->ownerable()->associate($pod)->save();
        $company->forceFill(['currant_workspace' => $podWorkspace->id])->save();

        $this->assertSame($own->id, $this->completion->companyWorkspace($company->fresh())->id);
        $this->assertFalse($this->completion->requiresOnboarding($company->fresh()));

        $employee = User::factory()->employee()->create();
        $admin = User::factory()->administrator()->create();
        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'x']);
        foreach ([$employee, $admin, $client, null] as $account) {
            $this->assertNull($this->completion->companyWorkspace($account));
            $this->assertFalse($this->completion->requiresOnboarding($account));
        }
    }

    // ================================================================ helpers

    private function fillSection(Workspace $workspace, string $section): void
    {
        $values = match ($section) {
            'company' => ['company' => 'Acme', 'logo' => $workspace->id . '_company-logo-x.png'],
            'industry' => ['industry_id' => Industry::create(['name' => 'Technology'])->id],
            'contact' => ['contact_name' => 'Casey', 'contact_email' => 'casey@example.test', 'telephone' => '1', 'city' => 'Colombo', 'country' => 'LK'],
            'online' => ['website' => 'https://acme.example.test', 'facebook' => 'https://facebook.com/acme'],
            'description' => ['description' => str_repeat('a', 100)],
            'matching' => ['matching_mode' => 'manual'],
            'approvals' => ['default_pod_application_approval' => 'manual', 'default_inner_circle_approval' => 'automatic'],
            'requirements' => [],
        };
        $workspace->forceFill($values)->save();

        if ($section === 'requirements') {
            $requirement = new WorkspaceWorkerRequirement([
                'job_title_id' => JobTitle::create(['title' => 'Dev'])->id, 'headcount' => 1,
                'preferred_work_mode' => 'hybrid', 'preferred_engagement_type' => 'full_time', 'is_active' => true,
            ]);
            $requirement->forceFill(['workspace_id' => $workspace->id])->save();
            $requirement->skills()->attach(Skill::create(['name' => 'Go'])->id);
        }
    }

    private function company(): User
    {
        $user = User::factory()->company()->create(['plan' => 1, 'email_verified_at' => now()]);
        $workspace = Workspace::create(['name' => "{$user->name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner']);
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return $user->fresh();
    }
}

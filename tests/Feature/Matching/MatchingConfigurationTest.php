<?php

namespace Tests\Feature\Matching;

use App\Enums\Matching\MatchCriterion as C;
use App\Enums\Profile\ProjectType;
use App\Models\PodWorkerRequirement;
use App\Models\WorkspaceWorkerRequirement;
use App\Services\Company\BusinessOnboardingService;
use App\Services\Matching\InvalidMatchingConfiguration;
use App\Services\Matching\MatchingConfiguration;

/** UNA-013: mandatory-criteria validation (request + domain) and template-to-POD snapshot copying. */
class MatchingConfigurationTest extends MatchingTestCase
{
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'job_title_id' => $this->role->id, 'headcount' => 2, 'skill_ids' => [$this->php->id],
            'preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract', 'is_active' => 1,
        ];
    }

    private function draft()
    {
        return $this->pod ??= $this->pods()->createDraft($this->company, ['title' => 'Platform Team']);
    }

    public function test_configured_criteria_follow_the_requirement_data(): void
    {
        $this->assertSame([C::SKILL_COVERAGE, C::ROLE, C::WORK_MODE, C::ENGAGEMENT_TYPE],
            MatchingConfiguration::configuredCriteria($this->row(), 1));
        $this->assertSame(C::cases(), MatchingConfiguration::configuredCriteria($this->row([
            'minimum_skill_proficiency' => 'beginner', 'minimum_years_experience' => '0.5', 'minimum_hours_per_week' => 10,
            'location' => 'Sri Lanka', 'project_type' => 'ongoing',
        ]), 1));
        $this->assertNotContains(C::EXPERIENCE, MatchingConfiguration::configuredCriteria($this->row(['minimum_years_experience' => 0]), 1), '0 years is no requirement');
        $this->assertNotContains(C::LOCATION, MatchingConfiguration::configuredCriteria($this->row(['location' => ' , - ']), 1), 'no normalized token');
        $this->assertContains(C::LOCATION, MatchingConfiguration::configuredCriteria($this->row(['timezone' => 'UTC']), 1));
        $this->assertNotContains(C::SKILL_PROFICIENCY, MatchingConfiguration::configuredCriteria($this->row(['minimum_skill_proficiency' => 'expert']), 0));
    }

    public function test_unknown_mandatory_keys_are_rejected_by_the_request_and_the_domain(): void
    {
        $pod = $this->draft();
        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), [
            'requirements_present' => 1,
            'requirements' => [$this->row(['mandatory_criteria' => ['role', 'salary']])],
        ])->assertSessionHasErrors('requirements.0.mandatory_criteria.1');
        $this->assertSame(0, PodWorkerRequirement::count());

        try {
            $this->pods()->updateDraft($pod, $this->company, ['requirements_present' => 1, 'requirements' => [$this->row(['mandatory_criteria' => ['salary']])]]);
            $this->fail('unknown criterion saved');
        } catch (InvalidMatchingConfiguration $e) {
            $this->assertArrayHasKey('requirements.0.mandatory_criteria', $e->errors());
        }
        $this->assertSame(0, PodWorkerRequirement::count(), 'rolled back');

        $this->actingAs($this->company)->post(route('company.onboarding.update'), [
            'step' => 'requirements', 'requirements_present' => 1, 'requirements' => [$this->row(['mandatory_criteria' => ['vibes']])],
        ])->assertSessionHasErrors('requirements.0.mandatory_criteria.0');
        $this->assertSame(0, WorkspaceWorkerRequirement::count());
    }

    public function test_only_configured_criteria_can_be_mandatory(): void
    {
        $pod = $this->draft();
        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), [
            'requirements_present' => 1,
            'requirements' => [$this->row(['mandatory_criteria' => ['role', 'project_type', 'experience']])],
        ])->assertSessionHasErrors(['requirements.0.mandatory_criteria' => 'Only configured criteria can be mandatory. Configure or unselect: Relevant experience, Project type.']);

        // Through the service (another caller): nothing is saved.
        $this->expectException(InvalidMatchingConfiguration::class);
        try {
            $this->pods()->updateDraft($pod, $this->company, ['requirements_present' => 1, 'requirements' => [$this->row(['skill_ids' => [], 'mandatory_criteria' => ['skill_coverage']])]]);
        } finally {
            $this->assertSame(0, PodWorkerRequirement::count());
        }
    }

    public function test_the_setup_form_saves_and_clears_matching_configuration(): void
    {
        $pod = $this->draft();
        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), [
            'requirements_present' => 1,
            'requirements' => [$this->row([
                'required_start_date' => '2026-07-01', 'minimum_hours_per_week' => 30, 'timezone' => 'Asia/Colombo', 'timezone_tolerance_hours' => 2,
                'project_type' => 'long_term', 'mandatory_criteria_present' => 1, 'mandatory_criteria' => ['project_type', 'role', 'role'],
            ])],
        ])->assertSessionHasErrors('requirements.0.mandatory_criteria.1'); // distinct

        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), [
            'requirements_present' => 1,
            'requirements' => [$this->row([
                'required_start_date' => '2026-07-01', 'minimum_hours_per_week' => 30, 'timezone' => 'Asia/Colombo', 'timezone_tolerance_hours' => 2,
                'project_type' => 'long_term', 'mandatory_criteria_present' => 1, 'mandatory_criteria' => ['project_type', 'role'],
            ])],
        ])->assertSessionHasNoErrors();
        $requirement = PodWorkerRequirement::sole();
        $this->assertSame(['2026-07-01', 30, 'Asia/Colombo', 2, ProjectType::LONG_TERM, ['role', 'project_type']],
            [$requirement->required_start_date->format('Y-m-d'), $requirement->minimum_hours_per_week, $requirement->timezone, $requirement->timezone_tolerance_hours, $requirement->project_type, $requirement->mandatory_criteria]);

        // Unticking every box (only the presence marker is sent) clears the selection.
        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), [
            'requirements_present' => 1,
            'requirements' => [$this->row(['id' => $requirement->id, 'mandatory_criteria_present' => 1])],
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $requirement->fresh()->mandatoryCriteria());

        foreach (['timezone' => 'Mars/Base', 'timezone_tolerance_hours' => 15, 'minimum_hours_per_week' => 169, 'project_type' => 'forever', 'required_start_date' => '01/07/2026'] as $field => $value) {
            $this->actingAs($this->company)->post(route('pods.setup.update', $pod), [
                'requirements_present' => 1, 'requirements' => [$this->row(['id' => $requirement->id, $field => $value])],
            ])->assertSessionHasErrors("requirements.0.{$field}");
        }
    }

    public function test_template_import_copies_matching_configuration_into_an_independent_snapshot(): void
    {
        $workspace = $this->company->ownWorkspace();
        app(BusinessOnboardingService::class)->save($this->company, [
            'step' => 'requirements', 'requirements_present' => 1,
            'requirements' => [$this->row([
                'minimum_skill_proficiency' => 'advanced', 'required_start_date' => '2026-08-01', 'minimum_hours_per_week' => 20,
                'timezone' => 'Europe/London', 'timezone_tolerance_hours' => 3, 'project_type' => 'ongoing', 'location' => 'London',
                'mandatory_criteria' => ['location', 'skill_proficiency', 'availability'],
            ])],
        ]);
        $template = WorkspaceWorkerRequirement::where('workspace_id', $workspace->id)->sole();
        $this->assertSame(['skill_proficiency', 'availability', 'location'], $template->mandatory_criteria, 'stored in declaration order');

        $pod = $this->pods()->createDraft($this->company, ['title' => 'Imported', 'import_template_ids' => [$template->id]]);
        $copy = $pod->workerRequirements->sole();
        $this->assertSame($template->id, $copy->source_workspace_requirement_id);
        $this->assertSame($template->matchingAttributes(), $copy->matchingAttributes());
        $this->assertSame([$this->php->id], $copy->skills->pluck('id')->all());

        // Later template edits never reach the POD snapshot.
        app(BusinessOnboardingService::class)->save($this->company, [
            'step' => 'requirements', 'requirements_present' => 1,
            'requirements' => [$this->row(['id' => $template->id, 'project_type' => 'hourly', 'timezone' => 'UTC', 'mandatory_criteria_present' => 1])],
        ]);
        $this->assertSame([ProjectType::HOURLY, 'UTC', []], [$template->fresh()->project_type, $template->fresh()->timezone, $template->fresh()->mandatoryCriteria()]);
        $copy = $copy->fresh();
        $this->assertSame([ProjectType::ONGOING, 'Europe/London', ['skill_proficiency', 'availability', 'location']], [$copy->project_type, $copy->timezone, $copy->mandatory_criteria]);

        // Both forms render the saved configuration (and the import button carries it for the JS copy).
        $this->actingAs($this->company)->get(route('pods.setup.edit', ['pod' => $pod, 'step' => 'requirements']))->assertOk()
            ->assertSee('name="requirements[0][mandatory_criteria][]"', false)
            ->assertSee('value="2026-08-01"', false)
            ->assertSee('&quot;mandatory_criteria&quot;:[]', false);
        $this->actingAs($this->company)->get(route('company.onboarding.edit', ['step' => 'requirements']))->assertOk()
            ->assertSee('name="requirements[0][mandatory_criteria_present]"', false)
            ->assertSee('<option value="UTC" selected>', false);
    }

}

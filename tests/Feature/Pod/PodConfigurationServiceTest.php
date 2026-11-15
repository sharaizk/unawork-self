<?php

namespace Tests\Feature\Pod;

use App\Models\Pod;
use App\Models\PodWorkerRequirement;
use App\Services\Pod\PodConfigurationService;
use PHPUnit\Framework\Attributes\DataProvider;

/** UNA-011: PodConfigurationService - five weighted sections, all or nothing, plus publication eligibility. */
class PodConfigurationServiceTest extends PodTestCase
{
    private const WEIGHTS = ['title' => 15, 'description' => 15, 'skills' => 20, 'requirements' => 30, 'matching' => 20];

    public function test_an_empty_draft_scores_zero_and_lists_everything(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => '']);
        $service = app(PodConfigurationService::class);
        $breakdown = $service->breakdown($pod);

        $this->assertSame([0, 100, false], [$breakdown['percentage'], $breakdown['required'], $breakdown['complete']]);
        $this->assertSame(self::WEIGHTS, array_map(fn ($s) => $s['weight'], $breakdown['sections']));
        $this->assertSame(100, array_sum(config('pod_onboarding.weights')));
        $this->assertCount(5, $service->missingRequirements($pod));
    }

    #[DataProvider('sections')]
    public function test_each_section_contributes_its_weight(string $section): void
    {
        $values = $this->completeConfiguration();
        $only = match ($section) {
            'title' => ['title' => 'Team'],
            'description' => ['title' => '', 'description' => $values['description']],
            'skills' => ['title' => '', 'skills' => $values['skills']],
            'requirements' => ['title' => '', 'requirements_present' => 1, 'requirements' => $values['requirements']],
            'matching' => ['title' => '', 'matching_mode' => 'automatic'],
        };

        $pod = $this->pods()->createDraft($this->company, $only);

        $this->assertSame(self::WEIGHTS[$section], app(PodConfigurationService::class)->percentage($pod), $section);
    }

    public static function sections(): array
    {
        return array_combine(array_keys(self::WEIGHTS), array_map(fn ($s) => [$s], array_keys(self::WEIGHTS)));
    }

    public function test_a_complete_configuration_scores_100_and_incomplete_requirements_do_not_count(): void
    {
        $pod = $this->configuredDraft();
        $service = app(PodConfigurationService::class);
        $this->assertSame(100, $service->percentage($pod));

        PodWorkerRequirement::query()->update(['preferred_engagement_type' => null]);
        $this->assertSame(70, $service->percentage(Pod::find($pod->id)));
        PodWorkerRequirement::query()->update(['preferred_engagement_type' => 'contract', 'is_active' => false]);
        $this->assertSame(70, $service->percentage(Pod::find($pod->id)));
    }

    public function test_can_publish_needs_ownership_configuration_company_profile_and_a_draft(): void
    {
        $service = app(PodConfigurationService::class);
        $pod = $this->configuredDraft();

        $this->assertFalse($service->canPublish($pod, $this->company), 'company profile incomplete');
        $this->completeBusinessProfile($this->company);
        $this->assertTrue($service->canPublish($pod->fresh(), $this->company->fresh()));
        $this->assertFalse($service->canPublish($pod->fresh(), $this->account('company')), 'another company');
        $this->assertFalse($service->canPublish($pod->fresh(), $this->account('employee')));

        $this->pods()->publish($pod, $this->company->fresh());
        $this->assertFalse($service->canPublish($pod->fresh(), $this->company->fresh()), 'already published');
    }
}

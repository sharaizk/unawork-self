<?php

namespace App\Services\Pod;

use App\Enums\Pod\PodMatchingMode;
use App\Models\Pod;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use App\Services\Company\BusinessOnboardingCompletionService;

/**
 * The single authoritative implementation of POD configuration completeness and publication
 * eligibility (UNA-011).
 *
 * Five weighted sections (config('pod_onboarding.weights')), each worth its full weight or nothing,
 * calculated from the POD and its requirements on every call; never stored. Publishing also needs
 * the owning company's business onboarding (UNA-010) to be complete right now.
 */
class PodConfigurationService
{
    public function __construct(private BusinessOnboardingCompletionService $companies)
    {
    }

    public function percentage(Pod $pod): int
    {
        return $this->score($this->sections($pod));
    }

    /**
     * @return array{percentage:int, required:int, complete:bool,
     *     sections: array<string, array{label:string, weight:int, complete:bool, missing:string[]}>}
     */
    public function breakdown(Pod $pod): array
    {
        $sections = $this->sections($pod);
        $percentage = $this->score($sections);

        return [
            'percentage' => $percentage,
            'required' => $this->required(),
            'complete' => $percentage >= $this->required(),
            'sections' => $sections,
        ];
    }

    public function isComplete(Pod $pod): bool
    {
        return $this->percentage($pod) >= $this->required();
    }

    /** @return string[] */
    public function missingRequirements(Pod $pod): array
    {
        return array_merge(...array_values(array_map(fn (array $s) => $s['missing'], $this->sections($pod))));
    }

    /** Draft, owned by $actor's company account, fully configured, and the company profile is complete. */
    public function canPublish(Pod $pod, User $actor): bool
    {
        return $pod->isDraft()
            && $this->isOwner($pod, $actor)
            && $this->isComplete($pod)
            && $this->companyComplete($actor);
    }

    public function isOwner(Pod $pod, ?User $actor): bool
    {
        return $actor !== null && $actor->isCompany() && (int) $pod->created_by === (int) $actor->id;
    }

    public function companyComplete(User $actor): bool
    {
        $workspace = $this->companies->companyWorkspace($actor);

        return $workspace !== null && $this->companies->isComplete($workspace);
    }

    /** @return array{percentage:int, required:int, missing:string[]} the owner's business onboarding */
    public function companyStatus(User $actor): array
    {
        $workspace = $this->companies->companyWorkspace($actor);
        if ($workspace === null) {
            return ['percentage' => 0, 'required' => $this->companies->required(), 'missing' => [__('Create your company profile')]];
        }

        return [
            'percentage' => $this->companies->percentage($workspace),
            'required' => $this->companies->required(),
            'missing' => $this->companies->missingRequirements($workspace),
        ];
    }

    /**
     * Sections that were complete in $before and are not any more (used to stop a published POD
     * from being edited into a worse configuration).
     *
     * @return string[] section keys
     */
    public function regressions(array $before, array $after): array
    {
        return array_keys(array_filter($before['sections'], fn (array $s, string $key) => $s['complete'] && !$after['sections'][$key]['complete'], ARRAY_FILTER_USE_BOTH));
    }

    public function required(): int
    {
        return (int) config('pod_onboarding.required_completion', 100);
    }

    // ------------------------------------------------------------------ sections

    private function sections(Pod $pod): array
    {
        $pod->loadMissing(['skills', 'workerRequirements.skills']);
        $minDescription = (int) config('pod_onboarding.minimum_description_length', 50);

        $checks = [
            'title' => [__('POD title'), [
                __('Enter a POD title') => trim((string) $pod->title) !== '',
            ]],
            'description' => [__('POD description'), [
                __('Describe the POD in at least :count characters', ['count' => $minDescription]) => mb_strlen(trim((string) $pod->description)) >= $minDescription,
            ]],
            'skills' => [__('POD skills'), [
                __('Select at least one POD skill') => $pod->skills->isNotEmpty(),
            ]],
            'requirements' => [__('Worker requirements'), [
                __('Add at least one active worker requirement with a role, headcount, skills, work mode and engagement type') => $pod->workerRequirements
                    ->contains(fn (PodWorkerRequirement $requirement) => $requirement->isComplete()),
            ]],
            'matching' => [__('Matching mode'), [
                __('Choose automatic or pending-approval matching') => $pod->matching_mode instanceof PodMatchingMode,
            ]],
        ];

        $weights = (array) config('pod_onboarding.weights', []);
        $sections = [];
        foreach ($checks as $key => [$label, $requirements]) {
            $missing = array_keys(array_filter($requirements, fn (bool $met) => !$met));
            $sections[$key] = [
                'label' => $label,
                'weight' => (int) ($weights[$key] ?? 0),
                'complete' => $missing === [],
                'missing' => $missing,
            ];
        }

        return $sections;
    }

    private function score(array $sections): int
    {
        return min(100, array_sum(array_map(fn (array $s) => $s['complete'] ? $s['weight'] : 0, $sections)));
    }
}

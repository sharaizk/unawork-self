<?php

namespace App\Services\Company;

use App\Contracts\HasAccountType;
use App\Enums\Company\ApprovalMode;
use App\Enums\Company\MatchingMode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceWorkerRequirement;

/**
 * The single authoritative implementation of company (business) onboarding completion (UNA-010).
 *
 * Eight weighted sections (config('company_onboarding.weights')), each worth its full weight or
 * nothing, calculated from the company workspace and its worker requirements on every call; the
 * percentage is never stored and business_onboarding_completed_at is history, not eligibility.
 * Only company accounts are subject to it.
 */
class BusinessOnboardingCompletionService
{
    public const POD_MORPH = 'App\\Models\\Pod';

    /** The company's own (non-POD) workspace, or null for non-companies. Never currant_workspace. */
    public function companyWorkspace(?HasAccountType $account): ?Workspace
    {
        if (!$account instanceof User || !$account->isCompany()) {
            return null;
        }
        $workspace = $account->ownWorkspace();

        return $workspace && $workspace->ownerable_type !== self::POD_MORPH ? $workspace : null;
    }

    public function percentage(Workspace $workspace): int
    {
        return $this->score($this->sections($workspace));
    }

    /**
     * @return array{percentage:int, required:int, complete:bool,
     *     sections: array<string, array{label:string, weight:int, complete:bool, missing:string[]}>}
     */
    public function breakdown(Workspace $workspace): array
    {
        $sections = $this->sections($workspace);
        $percentage = $this->score($sections);

        return [
            'percentage' => $percentage,
            'required' => $this->required(),
            'complete' => $percentage >= $this->required(),
            'sections' => $sections,
        ];
    }

    public function isComplete(Workspace $workspace): bool
    {
        return $this->percentage($workspace) >= $this->required();
    }

    /** @return string[] every missing mandatory item, in section order */
    public function missingRequirements(Workspace $workspace): array
    {
        return array_merge(...array_values(array_map(fn (array $s) => $s['missing'], $this->sections($workspace))));
    }

    /** A company whose own workspace is below the requirement (sent to /company/onboarding). */
    public function requiresOnboarding(?HasAccountType $account): bool
    {
        $workspace = $this->companyWorkspace($account);

        return $workspace !== null && !$this->isComplete($workspace);
    }

    public function required(): int
    {
        return (int) config('company_onboarding.required_completion', 100);
    }

    // ------------------------------------------------------------------ sections

    /** @return array<string, array{label:string, weight:int, complete:bool, missing:string[]}> */
    private function sections(Workspace $workspace): array
    {
        $workspace->loadMissing(['industry', 'workerRequirements.skills']);
        $filled = fn ($value) => trim((string) $value) !== '';
        $minDescription = (int) config('company_onboarding.minimum_description_length', 100);

        $checks = [
            'company' => [__('Company details'), [
                __('Enter your company name') => $filled($workspace->company),
                __('Upload your company logo') => $filled($workspace->logo),
            ]],
            'industry' => [__('Industry'), [
                __('Select your industry') => $workspace->industry_id !== null && $workspace->industry !== null,
            ]],
            'contact' => [__('Contact information'), [
                __('Enter a contact name') => $filled($workspace->contact_name),
                __('Enter a valid contact email') => filter_var((string) $workspace->contact_email, FILTER_VALIDATE_EMAIL) !== false,
                __('Enter a telephone number') => $filled($workspace->telephone),
                __('Enter your city') => $filled($workspace->city),
                __('Enter your country') => $filled($workspace->country),
            ]],
            'online' => [__('Website and professional links'), [
                __('Enter a valid website URL') => $this->validUrl($workspace->website),
                __('Add a LinkedIn or Facebook page') => $this->validUrl($workspace->linkedin) || $this->validUrl($workspace->facebook),
            ]],
            'description' => [__('Company description'), [
                __('Describe your company in at least :count characters', ['count' => $minDescription]) => mb_strlen(trim((string) $workspace->description)) >= $minDescription,
            ]],
            'requirements' => [__('Worker requirements'), [
                __('Add at least one active worker requirement with a role, headcount, skills, work mode and engagement type') => $workspace->workerRequirements
                    ->contains(fn (WorkspaceWorkerRequirement $requirement) => $requirement->isComplete()),
            ]],
            'matching' => [__('Matching mode'), [
                __('Choose a matching mode') => $workspace->matching_mode instanceof MatchingMode,
            ]],
            'approvals' => [__('Default approval preferences'), [
                __('Choose how POD applications are approved') => $workspace->default_pod_application_approval instanceof ApprovalMode,
                __('Choose how Inner Circle promotions are approved') => $workspace->default_inner_circle_approval instanceof ApprovalMode,
            ]],
        ];

        $weights = (array) config('company_onboarding.weights', []);
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

    private function validUrl(?string $url): bool
    {
        return is_string($url)
            && preg_match('#^https?://#i', $url) === 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

<?php

namespace App\Http\Requests\Company;

use App\Enums\Company\ApprovalMode;
use App\Enums\Company\MatchingMode;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Services\Company\BusinessOnboardingService;
use App\Http\Requests\Concerns\ValidatesMatchingConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Company profile / business onboarding form (UNA-010). Every field is optional so drafts save;
 * completeness is scored separately by BusinessOnboardingCompletionService. Requirement ids must
 * belong to the authenticated company's own workspace; workspace_id, created_by and ownerable_* are
 * never accepted.
 */
class UpdateBusinessOnboardingRequest extends FormRequest
{
    use ValidatesMatchingConfiguration; // UNA-013

    private const URL = ['nullable', 'string', 'max:500', 'url', 'starts_with:http://,https://'];

    private const REQUIREMENT_VALUE_FIELDS = [
        'job_title_id', 'headcount', 'minimum_years_experience', 'minimum_skill_proficiency',
        'preferred_work_mode', 'preferred_engagement_type', 'location', 'description',
    ];

    public function authorize(): bool
    {
        $workspace = app(BusinessOnboardingCompletionService::class)->companyWorkspace($this->user());

        return $workspace !== null && (bool) $this->user()->can('manageCompanyProfile', $workspace);
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('requirements');
        if (is_array($rows)) {
            // Drop rows the company added but left completely empty.
            $this->merge(['requirements' => array_filter($rows, function ($row) {
                if (!is_array($row) || (isset($row['id']) && $row['id'] !== '') || !empty($row['skill_ids'])) {
                    return true;
                }
                foreach (array_merge(self::REQUIREMENT_VALUE_FIELDS, self::matchingValueFields()) as $field) {
                    if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                        return true;
                    }
                }

                return false;
            })]);
        }
    }

    public function rules(): array
    {
        $workspaceId = (int) (app(BusinessOnboardingCompletionService::class)->companyWorkspace($this->user())?->id ?? 0);

        return [
            'step' => ['nullable', Rule::in(BusinessOnboardingService::STEPS)],
            'intent' => ['nullable', Rule::in(['draft', 'continue'])],

            'company' => ['nullable', 'string', 'max:255'],
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],

            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_position' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],

            'website' => self::URL,
            'linkedin' => self::URL,
            'facebook' => self::URL,

            'description' => ['nullable', 'string', 'max:10000'],

            'matching_mode' => ['nullable', new Enum(MatchingMode::class)],
            // UNA-014: default for new PODs only; empty falls back to config('pod_matching.default_minimum_threshold').
            'default_minimum_match_threshold' => ['nullable', 'integer', 'between:0,100'],
            'default_pod_application_approval' => ['nullable', new Enum(ApprovalMode::class)],
            'default_inner_circle_approval' => ['nullable', new Enum(ApprovalMode::class)],

            'requirements_present' => ['nullable', 'boolean'],
            'requirements' => ['nullable', 'array', 'max:' . (int) config('company_onboarding.max_worker_requirements', 20)],
            'requirements.*' => ['array'],
            'requirements.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('workspace_worker_requirements', 'id')->where('workspace_id', $workspaceId)],
            'requirements.*.job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'requirements.*.skill_ids' => ['nullable', 'array', 'max:30'],
            'requirements.*.skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
            'requirements.*.headcount' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('company_onboarding.max_headcount', 500)],
            'requirements.*.minimum_years_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'requirements.*.minimum_skill_proficiency' => ['nullable', new Enum(SkillProficiency::class)],
            'requirements.*.preferred_work_mode' => ['nullable', new Enum(WorkMode::class)],
            'requirements.*.preferred_engagement_type' => ['nullable', new Enum(EngagementType::class)],
            'requirements.*.location' => ['nullable', 'string', 'max:255'],
            'requirements.*.description' => ['nullable', 'string', 'max:2000'],
            'requirements.*.is_active' => ['nullable', 'boolean'],
        ] + $this->matchingRequirementRules();
    }

    public function attributes(): array
    {
        return [
            'company' => __('company name'),
            'industry_id' => __('industry'),
            'logo' => __('logo'),
            'contact_name' => __('contact name'),
            'contact_position' => __('contact position'),
            'contact_email' => __('contact email'),
            'telephone' => __('telephone'),
            'website' => __('website'),
            'linkedin' => __('LinkedIn URL'),
            'facebook' => __('Facebook URL'),
            'description' => __('company description'),
            'matching_mode' => __('matching mode'),
            'default_minimum_match_threshold' => __('default minimum match score'),
            'default_pod_application_approval' => __('POD application approval'),
            'default_inner_circle_approval' => __('Inner Circle approval'),
            'requirements.*.id' => __('worker requirement'),
            'requirements.*.job_title_id' => __('professional role'),
            'requirements.*.skill_ids' => __('skills'),
            'requirements.*.skill_ids.*' => __('skill'),
            'requirements.*.headcount' => __('headcount'),
            'requirements.*.minimum_years_experience' => __('minimum experience'),
            'requirements.*.minimum_skill_proficiency' => __('minimum proficiency'),
            'requirements.*.preferred_work_mode' => __('work mode'),
            'requirements.*.preferred_engagement_type' => __('engagement type'),
            'requirements.*.location' => __('location'),
            'requirements.*.description' => __('notes'),
        ] + $this->matchingRequirementAttributes();
    }
}

<?php

namespace App\Http\Requests\Pods;

use App\Enums\Pod\PodMatchingMode;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Models\Pod;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Http\Requests\Concerns\ValidatesMatchingConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Guided POD setup save (UNA-011). Everything is optional so drafts save at any percentage;
 * completeness is PodConfigurationService's job. Requirement ids must belong to this POD and
 * template ids to the owner's company workspace. created_by, pod_id, workspace_id, ownerable_*,
 * status and published_at are never accepted.
 */
class UpdatePodSetupRequest extends FormRequest
{
    use ValidatesMatchingConfiguration; // UNA-013

    public const STEPS = ['basics', 'skills', 'requirements', 'matching', 'review'];

    private const REQUIREMENT_VALUE_FIELDS = [
        'job_title_id', 'headcount', 'minimum_years_experience', 'minimum_skill_proficiency',
        'preferred_work_mode', 'preferred_engagement_type', 'location', 'description',
    ];

    public function authorize(): bool
    {
        $pod = $this->route('pod');

        return $pod instanceof Pod && (bool) $this->user()?->can('configure', $pod);
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('requirements');
        if (is_array($rows)) {
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
        $pod = $this->route('pod');
        $podId = $pod instanceof Pod ? (int) $pod->id : 0;
        $workspaceId = (int) (app(BusinessOnboardingCompletionService::class)->companyWorkspace($this->user())?->id ?? 0);
        $template = Rule::exists('workspace_worker_requirements', 'id')->where('workspace_id', $workspaceId);

        return [
            'step' => ['nullable', Rule::in(self::STEPS)],
            'intent' => ['nullable', Rule::in(['draft', 'continue'])],

            'title' => ['nullable', 'string', 'max:' . (int) config('pod_onboarding.max_title_length', 255)],
            'description' => ['nullable', 'string', 'max:' . (int) config('pod_onboarding.max_description_length', 5000)],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],

            'skills_present' => ['nullable', 'boolean'],
            'skills' => ['nullable', 'array', 'max:' . (int) config('pod_onboarding.max_skills', 30)],
            'skills.*' => ['integer', 'distinct', 'exists:skills,id'],

            'matching_mode' => ['nullable', new Enum(PodMatchingMode::class)],
            // UNA-014: the setup form always sends matching_present, so the threshold cannot be blanked.
            'matching_present' => ['nullable', 'boolean'],
            'minimum_match_threshold' => ['nullable', 'required_if_accepted:matching_present', 'integer', 'between:0,100'],

            'import_template_ids' => ['nullable', 'array', 'max:' . (int) config('pod_onboarding.max_requirements', 20)],
            'import_template_ids.*' => ['integer', 'distinct', $template],

            'requirements_present' => ['nullable', 'boolean'],
            'requirements' => ['nullable', 'array', 'max:' . (int) config('pod_onboarding.max_requirements', 20)],
            'requirements.*' => ['array'],
            'requirements.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('pod_worker_requirements', 'id')->where('pod_id', $podId)],
            'requirements.*.source_workspace_requirement_id' => ['nullable', 'integer', $template],
            'requirements.*.job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'requirements.*.skill_ids' => ['nullable', 'array', 'max:30'],
            'requirements.*.skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
            'requirements.*.headcount' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('pod_onboarding.max_headcount', 500)],
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
            'title' => __('POD title'),
            'description' => __('POD description'),
            'image' => __('image'),
            'skills.*' => __('skill'),
            'matching_mode' => __('matching mode'),
            'minimum_match_threshold' => __('minimum match score'),
            'import_template_ids.*' => __('worker requirement template'),
            'requirements.*.id' => __('worker requirement'),
            'requirements.*.source_workspace_requirement_id' => __('worker requirement template'),
            'requirements.*.job_title_id' => __('professional role'),
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

<?php

namespace App\Http\Requests\Profile;

use App\Enums\Profile\AvailabilityStatus;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\ProjectType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * The /my-profile form (UNA-007). Every field is optional so an employee can save a draft;
 * completeness is scored separately by ProfileCompletionService. Nested record ids must belong to
 * the authenticated employee's own profile; user_id / profile_id are never accepted.
 */
class UpdateMyProfileRequest extends FormRequest
{
    private const URL = ['nullable', 'string', 'max:500', 'url', 'starts_with:http://,https://'];

    /** Repeatable sections and the fields that make a row non-blank. */
    private const ROW_FIELDS = [
        'skills' => ['skill_id', 'proficiency'],
        'experiences' => ['employer', 'job_title', 'job_title_id', 'engagement_type', 'location', 'start_date', 'end_date', 'description'],
        'educations' => ['institution', 'degree', 'field_of_study', 'start_year', 'end_year', 'description'],
        'certifications' => ['name', 'issuing_organization', 'issue_date', 'expiration_date', 'credential_id', 'credential_url'],
        'portfolios' => ['title', 'url', 'description'],
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('editOwn', Profile::class);
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (self::ROW_FIELDS as $section => $fields) {
            $rows = $this->input($section);
            if (!is_array($rows)) {
                continue;
            }
            // Drop rows the employee added but left completely empty.
            $merge[$section] = array_filter($rows, function ($row) use ($fields) {
                if (!is_array($row)) {
                    return true; // let validation reject it
                }
                if (isset($row['id']) && $row['id'] !== '') {
                    return true;
                }
                foreach ($fields as $field) {
                    if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                        return true;
                    }
                }

                return false;
            });
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $profileId = (int) ($this->user()?->profile()->value('id') ?? 0);
        $owned = fn (string $table) => ['nullable', 'integer', 'distinct', Rule::exists($table, 'id')->where('profile_id', $profileId)];
        $maxYear = (int) date('Y') + 10;

        return [
            'sync' => ['nullable', 'array'],
            'sync.*' => [Rule::in(ProfileService::SYNCABLE)],

            'about' => ['nullable', 'string', 'max:25500'],
            // The industry only narrows the skill/role pickers; any catalog skill or role stays valid.
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'years_experience' => ['nullable', 'numeric', 'decimal:0,1', 'min:0', 'max:60'],

            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'timezone:all'],

            'availability_status' => ['nullable', new Enum(AvailabilityStatus::class)],
            'available_from' => ['nullable', 'date'],
            'hours_per_week' => ['nullable', 'integer', 'min:0', 'max:80'],

            'preferred_work_mode' => ['nullable', new Enum(WorkMode::class)],
            'preferred_engagement_type' => ['nullable', new Enum(EngagementType::class)],
            'open_to_relocation' => ['nullable', 'boolean'],
            'willing_to_travel' => ['nullable', 'boolean'],

            'linkedin_url' => self::URL,
            'github_url' => self::URL,
            'website' => self::URL,

            'cv_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,odt,txt', 'max:10240'],

            'skills' => ['nullable', 'array', 'max:50'],
            'skills.*' => ['array'],
            'skills.*.skill_id' => ['required', 'integer', 'distinct', 'exists:skills,id'],
            'skills.*.proficiency' => ['nullable', new Enum(SkillProficiency::class)],

            'experiences' => ['nullable', 'array', 'max:30'],
            'experiences.*' => ['array'],
            'experiences.*.id' => $owned('profile_experiences'),
            'experiences.*.employer' => ['required', 'string', 'max:255'],
            'experiences.*.job_title' => ['required', 'string', 'max:255'],
            'experiences.*.job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'experiences.*.engagement_type' => ['nullable', new Enum(EngagementType::class)],
            'experiences.*.location' => ['nullable', 'string', 'max:255'],
            'experiences.*.start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'experiences.*.end_date' => ['nullable', 'date'],
            'experiences.*.is_current' => ['nullable', 'boolean'],
            'experiences.*.description' => ['nullable', 'string', 'max:5000'],

            'educations' => ['nullable', 'array', 'max:20'],
            'educations.*' => ['array'],
            'educations.*.id' => $owned('profile_educations'),
            'educations.*.institution' => ['required', 'string', 'max:255'],
            'educations.*.degree' => ['nullable', 'string', 'max:255'],
            'educations.*.field_of_study' => ['nullable', 'string', 'max:255'],
            'educations.*.start_year' => ['nullable', 'integer', 'min:1950', 'max:' . $maxYear],
            'educations.*.end_year' => ['nullable', 'integer', 'min:1950', 'max:' . $maxYear],
            'educations.*.description' => ['nullable', 'string', 'max:5000'],

            'certifications' => ['nullable', 'array', 'max:30'],
            'certifications.*' => ['array'],
            'certifications.*.id' => $owned('profile_certifications'),
            'certifications.*.name' => ['required', 'string', 'max:255'],
            'certifications.*.issuing_organization' => ['nullable', 'string', 'max:255'],
            'certifications.*.issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'certifications.*.expiration_date' => ['nullable', 'date'],
            'certifications.*.credential_id' => ['nullable', 'string', 'max:255'],
            'certifications.*.credential_url' => self::URL,

            'portfolios' => ['nullable', 'array', 'max:20'],
            'portfolios.*' => ['array'],
            'portfolios.*.id' => $owned('profile_portfolios'),
            'portfolios.*.title' => ['required', 'string', 'max:255'],
            'portfolios.*.url' => ['required', 'string', 'max:500', 'url', 'starts_with:http://,https://'],
            'portfolios.*.description' => ['nullable', 'string', 'max:2000'],

            'preferred_role_ids' => ['nullable', 'array', 'max:20'],
            'preferred_role_ids.*' => ['integer', 'distinct', 'exists:job_titles,id'],

            'project_types' => ['nullable', 'array'],
            'project_types.*' => ['distinct', new Enum(ProjectType::class)],
        ];
    }

    /** Date ordering and the "current position" rule, checked per row. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            foreach ((array) ($data['experiences'] ?? []) as $i => $row) {
                $current = filter_var($row['is_current'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($current && !empty($row['end_date'])) {
                    $validator->errors()->add("experiences.$i.end_date", __('Leave the end date empty for your current position.'));
                } elseif ($this->before($row['end_date'] ?? null, $row['start_date'] ?? null)) {
                    $validator->errors()->add("experiences.$i.end_date", __('The end date must be on or after the start date.'));
                }
            }
            foreach ((array) ($data['educations'] ?? []) as $i => $row) {
                if (is_numeric($row['start_year'] ?? null) && is_numeric($row['end_year'] ?? null) && (int) $row['end_year'] < (int) $row['start_year']) {
                    $validator->errors()->add("educations.$i.end_year", __('The end year must be on or after the start year.'));
                }
            }
            foreach ((array) ($data['certifications'] ?? []) as $i => $row) {
                if ($this->before($row['expiration_date'] ?? null, $row['issue_date'] ?? null)) {
                    $validator->errors()->add("certifications.$i.expiration_date", __('The expiration date must be on or after the issue date.'));
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'about' => __('professional summary'),
            'industry_id' => __('industry'),
            'job_title_id' => __('current job title'),
            'years_experience' => __('years of experience'),
            'availability_status' => __('availability'),
            'available_from' => __('available from'),
            'hours_per_week' => __('hours per week'),
            'preferred_work_mode' => __('work mode'),
            'preferred_engagement_type' => __('engagement type'),
            'linkedin_url' => __('LinkedIn URL'),
            'github_url' => __('GitHub URL'),
            'website' => __('website'),
            'cv_file' => __('CV'),
            'skills.*.skill_id' => __('skill'),
            'skills.*.proficiency' => __('proficiency'),
            'experiences.*.id' => __('experience'),
            'experiences.*.employer' => __('employer'),
            'experiences.*.job_title' => __('job title'),
            'experiences.*.start_date' => __('start date'),
            'experiences.*.end_date' => __('end date'),
            'educations.*.id' => __('education'),
            'educations.*.institution' => __('institution'),
            'educations.*.start_year' => __('start year'),
            'educations.*.end_year' => __('end year'),
            'certifications.*.id' => __('certification'),
            'certifications.*.name' => __('certification name'),
            'certifications.*.issue_date' => __('issue date'),
            'certifications.*.credential_url' => __('credential URL'),
            'portfolios.*.id' => __('portfolio item'),
            'portfolios.*.title' => __('portfolio title'),
            'portfolios.*.url' => __('portfolio URL'),
            'preferred_role_ids.*' => __('preferred role'),
            'project_types.*' => __('project type'),
        ];
    }

    private function before(mixed $end, mixed $start): bool
    {
        if (!is_string($end) || !is_string($start) || $end === '' || $start === '' || strtotime($end) === false || strtotime($start) === false) {
            return false;
        }

        return strtotime($end) < strtotime($start);
    }
}

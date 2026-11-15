<?php

namespace App\Http\Requests\Employee;

use App\Enums\Profile\AvailabilityStatus;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\ProjectType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use Illuminate\Foundation\Http\FormRequest;

class FilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'email' => 'string|max:255',
            'country' => 'string|max:255',
            'state' => 'string|max:255',
            'city' => 'string|max:255',
            'zip_code' => 'string|max:10',
            'address' => 'string|max:255',
            'phone_number' => 'string|max:20',
            'skill_ids' => 'nullable|array',
            'skill_ids.*' => 'integer|exists:skills,id',
        ] + self::structuredRules();
    }

    /**
     * UNA-007 structured matching filters (all optional). Shared with HirePageController@search.
     * Enum filters accept a single value or a list.
     */
    public static function structuredRules(): array
    {
        $oneOrMany = fn (string $enum) => [
            'nullable',
            function (string $attribute, mixed $value, \Closure $fail) use ($enum) {
                foreach ((array) $value as $item) {
                    if (!is_string($item) || $enum::tryFrom($item) === null) {
                        $fail(__('The selected :attribute is invalid.', ['attribute' => str_replace('_', ' ', $attribute)]));

                        return;
                    }
                }
            },
        ];

        return [
            'job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'skill_proficiency' => $oneOrMany(SkillProficiency::class),
            'min_years_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'max_years_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'preferred_role_ids' => ['nullable', 'array'],
            'preferred_role_ids.*' => ['integer', 'exists:job_titles,id'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'availability_status' => $oneOrMany(AvailabilityStatus::class),
            'project_types' => $oneOrMany(ProjectType::class),
            'preferred_work_mode' => $oneOrMany(WorkMode::class),
            'preferred_engagement_type' => $oneOrMany(EngagementType::class),
        ];
    }
}

<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Matching\MatchCriterion;
use App\Enums\Profile\ProjectType;
use App\Services\Matching\MatchingConfiguration;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * UNA-013 matching fields of a `requirements.*` row, shared by the company onboarding and POD setup
 * requests. Unknown criterion keys fail the Enum rule; a mandatory criterion the row does not
 * configure fails the after-hook (the services enforce the same rule again).
 */
trait ValidatesMatchingConfiguration
{
    /** Request fields that make a submitted requirement row non-empty. */
    protected static function matchingValueFields(): array
    {
        return ['required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type'];
    }

    protected function matchingRequirementRules(): array
    {
        return [
            'requirements.*.required_start_date' => ['nullable', 'date_format:Y-m-d'],
            'requirements.*.minimum_hours_per_week' => ['nullable', 'integer', 'min:1', 'max:168'],
            'requirements.*.timezone' => ['nullable', 'string', 'timezone:all'],
            'requirements.*.timezone_tolerance_hours' => ['nullable', 'integer', 'min:0', 'max:' . (int) config('pod_matching.max_timezone_tolerance_hours', 14)],
            'requirements.*.project_type' => ['nullable', new Enum(ProjectType::class)],
            'requirements.*.mandatory_criteria_present' => ['nullable', 'boolean'],
            'requirements.*.mandatory_criteria' => ['nullable', 'array', 'max:' . count(MatchCriterion::cases())],
            'requirements.*.mandatory_criteria.*' => ['string', 'distinct', new Enum(MatchCriterion::class)],
        ];
    }

    protected function matchingRequirementAttributes(): array
    {
        return [
            'requirements.*.required_start_date' => __('required start date'),
            'requirements.*.minimum_hours_per_week' => __('minimum hours per week'),
            'requirements.*.timezone' => __('timezone'),
            'requirements.*.timezone_tolerance_hours' => __('timezone tolerance'),
            'requirements.*.project_type' => __('project type'),
            'requirements.*.mandatory_criteria' => __('mandatory criteria'),
            'requirements.*.mandatory_criteria.*' => __('mandatory criterion'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = $this->input('requirements');
            if (!is_array($rows)) {
                return;
            }
            foreach ($rows as $index => $row) {
                $key = "requirements.{$index}.mandatory_criteria";
                if (!is_array($row) || empty($row['mandatory_criteria']) || !is_array($row['mandatory_criteria'])
                    || $validator->errors()->has($key) || $validator->errors()->has("{$key}.*")) {
                    continue;
                }
                $skillCount = is_array($row['skill_ids'] ?? null) ? count(array_unique($row['skill_ids'])) : 0;
                $unconfigured = MatchingConfiguration::unconfiguredMandatory($row, $skillCount, $row['mandatory_criteria']);
                if ($unconfigured !== []) {
                    $validator->errors()->add($key, __('Only configured criteria can be mandatory. Configure or unselect: :criteria.', [
                        'criteria' => implode(', ', array_map(fn (MatchCriterion $c) => $c->label(), $unconfigured)),
                    ]));
                }
            }
        });
    }
}

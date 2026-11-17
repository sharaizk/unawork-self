<?php

namespace App\Models\Concerns;

use App\Enums\Matching\MatchCriterion;
use App\Enums\Profile\ProjectType;

/**
 * UNA-013 matching targets shared by company templates (WorkspaceWorkerRequirement) and POD-owned
 * snapshots (PodWorkerRequirement). Which criteria are configured, and whether the mandatory
 * selection is valid, is decided by App\Services\Matching\MatchingConfiguration only.
 */
trait HasMatchingConfiguration
{
    /**
     * Columns copied from a company template into a POD snapshot (besides the UNA-010 fields).
     * A static method rather than a trait constant, which needs PHP 8.2.
     *
     * @return string[]
     */
    public static function matchingFields(): array
    {
        return ['required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type', 'mandatory_criteria'];
    }

    public function initializeHasMatchingConfiguration(): void
    {
        $this->mergeFillable(self::matchingFields());
        $this->mergeCasts([
            'required_start_date' => 'date:Y-m-d',
            'minimum_hours_per_week' => 'integer',
            'timezone_tolerance_hours' => 'integer',
            'project_type' => ProjectType::class,
            'mandatory_criteria' => 'array',
        ]);
    }

    /**
     * Mandatory criteria as enums, unique, in MatchCriterion declaration order. Unknown stored values
     * are ignored here (they cannot be saved through the services).
     *
     * @return MatchCriterion[]
     */
    public function mandatoryCriteria(): array
    {
        return self::normalizeCriteria((array) ($this->mandatory_criteria ?? []));
    }

    public function isMandatory(MatchCriterion $criterion): bool
    {
        return in_array($criterion, $this->mandatoryCriteria(), true);
    }

    /**
     * @param array<int, string|MatchCriterion|null> $values
     * @return MatchCriterion[]
     */
    public static function normalizeCriteria(array $values): array
    {
        $wanted = [];
        foreach ($values as $value) {
            $criterion = $value instanceof MatchCriterion ? $value : MatchCriterion::tryFrom((string) $value);
            if ($criterion) {
                $wanted[$criterion->value] = true;
            }
        }

        return array_values(array_filter(MatchCriterion::cases(), fn (MatchCriterion $c) => isset($wanted[$c->value])));
    }

    /** Matching attributes of this requirement in a form that can be filled into another model. */
    public function matchingAttributes(): array
    {
        return [
            'required_start_date' => $this->required_start_date?->format('Y-m-d'),
            'minimum_hours_per_week' => $this->minimum_hours_per_week,
            'timezone' => $this->timezone,
            'timezone_tolerance_hours' => $this->timezone_tolerance_hours,
            'project_type' => $this->project_type?->value,
            'mandatory_criteria' => array_map(fn (MatchCriterion $c) => $c->value, $this->mandatoryCriteria()),
        ];
    }
}

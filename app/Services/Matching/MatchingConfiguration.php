<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchCriterion;
use App\Models\PodWorkerRequirement;
use App\Models\WorkspaceWorkerRequirement;

/**
 * The single definition of which matching criteria a worker requirement configures (UNA-013), and
 * of the rule that only a configured criterion may be mandatory. Used by the scorer, the form
 * requests and every service that writes requirements (BusinessOnboardingService, PodService).
 *
 * A criterion is configured ("active") when the requirement holds enough data to evaluate it:
 *   skill_coverage     at least one required skill
 *   skill_proficiency  at least one required skill and a minimum proficiency
 *   experience         a role and a minimum of more than 0 years
 *   role               a role
 *   availability       a required start date and/or minimum weekly hours above 0
 *   location           a location with at least one normalized token and/or a timezone
 *   project_type       a project type
 *   work_mode          a work mode (unweighted compatibility check)
 *   engagement_type    an engagement type (unweighted compatibility check)
 */
class MatchingConfiguration
{
    /**
     * @param array $attributes requirement attributes (enums or raw values)
     * @return MatchCriterion[] in declaration order
     */
    public static function configuredCriteria(array $attributes, int $skillCount): array
    {
        $has = fn (string $key) => self::filled($attributes[$key] ?? null);
        $positive = fn (string $key) => is_numeric($attributes[$key] ?? null) && (float) $attributes[$key] > 0;

        $configured = [
            MatchCriterion::SKILL_COVERAGE->value => $skillCount > 0,
            MatchCriterion::SKILL_PROFICIENCY->value => $skillCount > 0 && $has('minimum_skill_proficiency'),
            MatchCriterion::EXPERIENCE->value => $has('job_title_id') && $positive('minimum_years_experience'),
            MatchCriterion::ROLE->value => $has('job_title_id'),
            MatchCriterion::AVAILABILITY->value => $has('required_start_date') || $positive('minimum_hours_per_week'),
            MatchCriterion::LOCATION->value => LocationNormalizer::tokens((string) ($attributes['location'] ?? '')) !== [] || $has('timezone'),
            MatchCriterion::PROJECT_TYPE->value => $has('project_type'),
            MatchCriterion::WORK_MODE->value => $has('preferred_work_mode'),
            MatchCriterion::ENGAGEMENT_TYPE->value => $has('preferred_engagement_type'),
        ];

        return array_values(array_filter(MatchCriterion::cases(), fn (MatchCriterion $c) => $configured[$c->value]));
    }

    /** @return MatchCriterion[] */
    public static function configuredFor(PodWorkerRequirement|WorkspaceWorkerRequirement $requirement): array
    {
        $skillCount = $requirement->relationLoaded('skills') ? $requirement->skills->count() : $requirement->skills()->count();

        return self::configuredCriteria($requirement->attributesToArray(), $skillCount);
    }

    public static function isConfigured(PodWorkerRequirement|WorkspaceWorkerRequirement $requirement, MatchCriterion $criterion): bool
    {
        return in_array($criterion, self::configuredFor($requirement), true);
    }

    /**
     * Mandatory criteria that are not configured.
     *
     * @param array<int, string|MatchCriterion> $mandatory
     * @return MatchCriterion[]
     */
    public static function unconfiguredMandatory(array $attributes, int $skillCount, array $mandatory): array
    {
        $configured = self::configuredCriteria($attributes, $skillCount);

        return array_values(array_filter(
            PodWorkerRequirement::normalizeCriteria($mandatory),
            fn (MatchCriterion $c) => !in_array($c, $configured, true),
        ));
    }

    /**
     * Add mandatory_criteria to requirement attributes when the submitted row carries it (the key,
     * or the form's `mandatory_criteria_present` marker for "nothing ticked"). Known keys are stored
     * unique and in declaration order; unknown keys are kept so assertValid() rejects them.
     */
    public static function withMandatory(array $attributes, array $row): array
    {
        if (!array_key_exists('mandatory_criteria', $row) && empty($row['mandatory_criteria_present'])) {
            return $attributes;
        }

        $values = array_values(array_unique(array_map('strval', array_filter((array) ($row['mandatory_criteria'] ?? []), fn ($v) => $v !== null && $v !== ''))));
        $known = PodWorkerRequirement::normalizeCriteria($values);
        $attributes['mandatory_criteria'] = count($known) === count($values)
            ? array_map(fn (MatchCriterion $c) => $c->value, $known)
            : $values;

        return $attributes;
    }

    /**
     * Domain guard used by every write path after the requirement and its skills are saved (inside
     * the caller's transaction, so throwing rolls the save back). Unknown stored keys are rejected too.
     *
     * @throws InvalidMatchingConfiguration
     */
    public static function assertValid(PodWorkerRequirement|WorkspaceWorkerRequirement $requirement, string $errorKey = 'mandatory_criteria'): void
    {
        $stored = (array) ($requirement->mandatory_criteria ?? []);
        $unknown = array_values(array_filter($stored, fn ($value) => MatchCriterion::tryFrom((string) $value) === null));
        if ($unknown !== []) {
            throw InvalidMatchingConfiguration::unknown($errorKey, $unknown);
        }

        $requirement->unsetRelation('skills');
        $unconfigured = array_values(array_filter(
            $requirement->mandatoryCriteria(),
            fn (MatchCriterion $c) => !self::isConfigured($requirement, $c),
        ));
        if ($unconfigured !== []) {
            throw InvalidMatchingConfiguration::notConfigured($errorKey, $unconfigured);
        }
    }

    private static function filled(mixed $value): bool
    {
        if ($value instanceof \UnitEnum || $value instanceof \DateTimeInterface) {
            return true;
        }

        return $value !== null && trim((string) $value) !== '';
    }
}

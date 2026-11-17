<?php

namespace App\Enums\Matching;

/**
 * POD matching criteria (UNA-013): stable keys stored in pod_worker_requirements.mandatory_criteria
 * and pod_match_reasons.criterion. Declaration order is the display/evaluation order.
 *
 * The first seven are the weighted score (config('pod_matching.weights')). WORK_MODE and
 * ENGAGEMENT_TYPE are unweighted compatibility checks: they never change the score, they are only
 * explained and may be made mandatory by the business.
 */
enum MatchCriterion: string
{
    case SKILL_COVERAGE = 'skill_coverage';
    case SKILL_PROFICIENCY = 'skill_proficiency';
    case EXPERIENCE = 'experience';
    case ROLE = 'role';
    case AVAILABILITY = 'availability';
    case LOCATION = 'location';
    case PROJECT_TYPE = 'project_type';
    case WORK_MODE = 'work_mode';
    case ENGAGEMENT_TYPE = 'engagement_type';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /** @return array<string, string> value => translated label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function isWeighted(): bool
    {
        return !in_array($this, [self::WORK_MODE, self::ENGAGEMENT_TYPE], true);
    }

    /** Configured weight (0 for compatibility checks). */
    public function weight(): int
    {
        return $this->isWeighted() ? max(0, (int) config('pod_matching.weights.' . $this->value, 0)) : 0;
    }

    /** 0-based position for ordered display. */
    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::SKILL_COVERAGE => __('Required skills'),
            self::SKILL_PROFICIENCY => __('Skill proficiency'),
            self::EXPERIENCE => __('Relevant experience'),
            self::ROLE => __('Professional role'),
            self::AVAILABILITY => __('Availability'),
            self::LOCATION => __('Location and timezone'),
            self::PROJECT_TYPE => __('Project type'),
            self::WORK_MODE => __('Work mode'),
            self::ENGAGEMENT_TYPE => __('Engagement type'),
        };
    }
}

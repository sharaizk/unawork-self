<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchCriterion;
use App\Enums\Matching\MatchReasonCode as Code;
use App\Enums\Matching\MatchStatus;
use App\Enums\Profile\AvailabilityStatus;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Models\PodWorkerRequirement;
use App\Models\Profile;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The only POD matching scoring code (UNA-013). Pure: it reads the already-loaded models passed in
 * (see REQUIREMENT_RELATIONS / CANDIDATE_RELATIONS; a missing relation is a LogicException, never a
 * lazy query) and a fixed evaluation time, and returns a MatchResult. It writes nothing.
 *
 * Every criterion yields an attainment fraction num/den in [0, 1]. Awarded points are
 * round_half_up(weight * num * 100 / den) hundredths of a point; the final score is
 * round_half_up(sum(awarded hundredths) * 100 / sum(active weights)) hundredths, i.e. the active
 * weights are renormalized to 100. See docs/pod-matching.md for every rule.
 */
class PodMatchScorer
{
    public const REQUIREMENT_RELATIONS = ['skills', 'jobTitle'];

    public const CANDIDATE_RELATIONS = ['skills', 'profile.experiences', 'profile.preferredRoles', 'profile.projectTypes'];

    // 365.25 days per year, scaled by 100 so year comparisons stay in integers.
    private const DAYS_PER_YEAR_X100 = 36525;

    public function score(PodWorkerRequirement $requirement, User $candidate, DateTimeInterface $evaluatedAt): MatchResult
    {
        $this->assertLoaded($requirement, $candidate);

        $at = CarbonImmutable::instance($evaluatedAt);
        $profile = $candidate->profile;
        $configured = MatchingConfiguration::configuredFor($requirement);
        $mandatory = $requirement->mandatoryCriteria();

        $results = [];
        $failedMandatory = [];
        $activeWeight = 0;
        $awardedTotal = 0;

        foreach (MatchCriterion::cases() as $criterion) {
            $isMandatory = in_array($criterion, $mandatory, true);
            $possible = $criterion->weight();

            if (!in_array($criterion, $configured, true)) {
                $results[] = new CriterionResult($criterion, false, $possible, 0, false, $isMandatory, false, Code::NOT_CONFIGURED);
                continue;
            }

            [$num, $den, $code, $context] = $this->evaluate($criterion, $requirement, $candidate, $profile, $at);
            $passed = $num >= $den;
            $awarded = self::roundDiv($possible * $num * 100, $den);
            $disqualifying = $isMandatory && !$passed;

            if ($disqualifying) {
                $failedMandatory[] = $criterion;
            }
            $activeWeight += $possible;
            $awardedTotal += $awarded;

            $results[] = new CriterionResult($criterion, true, $possible, $awarded, $passed, $isMandatory, $disqualifying, $code, $context);
        }

        $score = $activeWeight > 0 ? min(10000, self::roundDiv($awardedTotal * 100, $activeWeight)) : 0;
        $status = match (true) {
            $failedMandatory !== [] => MatchStatus::DISQUALIFIED,
            $activeWeight === 0 => MatchStatus::NOT_SCORABLE,
            default => MatchStatus::ELIGIBLE,
        };

        return new MatchResult($score, $status, $activeWeight, $this->version(), $at, $results, $failedMandatory);
    }

    public function version(): string
    {
        return (string) config('pod_matching.version', 'una-013.1');
    }

    /** Non-negative integer division rounded half up. */
    public static function roundDiv(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    public static function formatHundredths(int $hundredths): string
    {
        return intdiv($hundredths, 100) . '.' . str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ criteria

    /** @return array{0:int, 1:int, 2:Code, 3:array} attainment numerator, denominator, code, context */
    private function evaluate(MatchCriterion $criterion, PodWorkerRequirement $requirement, User $candidate, Profile $profile, CarbonImmutable $at): array
    {
        return match ($criterion) {
            MatchCriterion::SKILL_COVERAGE => $this->skillCoverage($requirement, $candidate),
            MatchCriterion::SKILL_PROFICIENCY => $this->skillProficiency($requirement, $candidate),
            MatchCriterion::EXPERIENCE => $this->experience($requirement, $profile, $at),
            MatchCriterion::ROLE => $this->role($requirement, $profile),
            MatchCriterion::AVAILABILITY => $this->availability($requirement, $profile, $at),
            MatchCriterion::LOCATION => $this->location($requirement, $profile, $at),
            MatchCriterion::PROJECT_TYPE => $this->projectType($requirement, $profile),
            MatchCriterion::WORK_MODE => $this->workMode($requirement, $profile),
            MatchCriterion::ENGAGEMENT_TYPE => $this->engagement($requirement, $profile),
        };
    }

    /** matched required skills / required skills, by skill id only. */
    private function skillCoverage(PodWorkerRequirement $requirement, User $candidate): array
    {
        $held = $candidate->skills->keyBy('id');
        $matched = [];
        $missing = [];
        foreach ($this->requiredSkills($requirement) as $skill) {
            $entry = ['id' => (int) $skill->id, 'name' => (string) $skill->name];
            $held->has($skill->id) ? $matched[] = $entry : $missing[] = $entry;
        }
        $total = count($matched) + count($missing);
        $code = match (true) {
            $missing === [] => Code::SKILLS_ALL_MATCHED,
            $matched === [] => Code::SKILLS_NONE_MATCHED,
            default => Code::SKILLS_SOME_MISSING,
        };

        return [count($matched), $total, $code, [
            'required_count' => $total,
            'matched_count' => count($matched),
            'matched' => $matched,
            'missing' => $missing,
        ]];
    }

    /**
     * sum(min(actual rank, required rank)) / (required rank * required skills). A required skill the
     * candidate does not hold, or holds with a null (legacy) proficiency, counts as rank 0.
     */
    private function skillProficiency(PodWorkerRequirement $requirement, User $candidate): array
    {
        $required = $requirement->minimum_skill_proficiency;
        $requiredRank = $required->rank();
        $held = $candidate->skills->keyBy('id');

        $skills = [];
        $sum = 0;
        $met = 0;
        foreach ($this->requiredSkills($requirement) as $skill) {
            $actual = $held->has($skill->id) ? SkillProficiency::tryFrom((string) $held->get($skill->id)->pivot->proficiency) : null;
            $rank = $actual?->rank() ?? 0;
            $sum += min($rank, $requiredRank);
            $isMet = $rank >= $requiredRank;
            $met += $isMet ? 1 : 0;
            $skills[] = [
                'id' => (int) $skill->id,
                'name' => (string) $skill->name,
                'held' => $held->has($skill->id),
                'actual' => $actual?->value,
                'met' => $isMet,
            ];
        }
        $count = count($skills);
        $code = match (true) {
            $met === $count => Code::PROFICIENCY_MET,
            $sum === 0 => Code::PROFICIENCY_NOT_MET,
            default => Code::PROFICIENCY_PARTIAL,
        };

        return [$sum, $requiredRank * $count, $code, [
            'required' => $required->value,
            'met_count' => $met,
            'required_count' => $count,
            'skills' => $skills,
        ]];
    }

    /**
     * Union of the date ranges of experiences with the requirement's job_title_id (overlaps counted
     * once, current/open ends at the evaluation date), compared with the minimum in days
     * (365.25 days per year), capped at the minimum. Without such measurable experience the
     * aggregate profiles.years_experience is used, only when the primary role is the required role.
     */
    private function experience(PodWorkerRequirement $requirement, Profile $profile, CarbonImmutable $at): array
    {
        $roleId = (int) $requirement->job_title_id;
        $minimumTenths = self::tenths($requirement->minimum_years_experience);
        $requiredYears = self::formatTenths($minimumTenths);
        $today = self::dayNumber($at->format('Y-m-d'));

        $intervals = [];
        $skipped = 0;
        foreach ($profile->experiences as $experience) {
            if ((int) $experience->job_title_id !== $roleId) {
                continue;
            }
            $start = $experience->start_date ? self::dayNumber($experience->start_date->format('Y-m-d')) : null;
            $end = $experience->is_current ? $today : ($experience->end_date ? self::dayNumber($experience->end_date->format('Y-m-d')) : null);
            if ($start === null || $end === null) {
                $skipped++;
                continue;
            }
            $end = min($end, $today);
            if ($end <= $start) {
                $skipped++;
                continue;
            }
            $intervals[] = [$start, $end];
        }

        if ($intervals !== []) {
            $days = self::unionDays($intervals);
            $den = $minimumTenths * self::DAYS_PER_YEAR_X100;
            $num = min($days * 1000, $den);
            $context = [
                'source' => 'structured',
                'fallback_used' => false,
                'relevant_days' => $days,
                'relevant_years' => self::formatTenths(self::roundDiv($days * 1000, self::DAYS_PER_YEAR_X100)),
                'required_years' => $requiredYears,
                'experience_count' => count($intervals),
                'skipped_count' => $skipped,
            ];
        } elseif ((int) $profile->job_title_id === $roleId && $profile->years_experience !== null) {
            $yearsTenths = self::tenths($profile->years_experience);
            $den = $minimumTenths;
            $num = min($yearsTenths, $den);
            $context = [
                'source' => 'aggregate_fallback',
                'fallback_used' => true,
                'relevant_days' => null,
                'relevant_years' => self::formatTenths($yearsTenths),
                'required_years' => $requiredYears,
                'experience_count' => 0,
                'skipped_count' => $skipped,
            ];
        } else {
            $den = $minimumTenths;
            $num = 0;
            $context = [
                'source' => 'none',
                'fallback_used' => false,
                'relevant_days' => 0,
                'relevant_years' => '0.0',
                'required_years' => $requiredYears,
                'experience_count' => 0,
                'skipped_count' => $skipped,
            ];
        }

        $code = match (true) {
            $num >= $den => Code::EXPERIENCE_MET,
            $num > 0 => Code::EXPERIENCE_PARTIAL,
            default => Code::EXPERIENCE_NONE,
        };

        return [$num, $den, $code, $context + ['job_title' => $this->jobTitle($requirement)]];
    }

    /** Full points when the required role is the primary role or a preferred role. */
    private function role(PodWorkerRequirement $requirement, Profile $profile): array
    {
        $roleId = (int) $requirement->job_title_id;
        $historical = $profile->experiences->contains(fn ($e) => (int) $e->job_title_id === $roleId);
        $code = match (true) {
            (int) $profile->job_title_id === $roleId => Code::ROLE_PRIMARY,
            $profile->preferredRoles->contains(fn ($r) => (int) $r->job_title_id === $roleId) => Code::ROLE_PREFERRED,
            $historical => Code::ROLE_HISTORICAL_ONLY,
            default => Code::ROLE_MISMATCH,
        };
        $passed = in_array($code, [Code::ROLE_PRIMARY, Code::ROLE_PREFERRED], true);

        return [$passed ? 1 : 0, 1, $code, [
            'job_title' => $this->jobTitle($requirement),
            'has_historical_experience' => $historical,
        ]];
    }

    /**
     * Configured checks: start (available on or before max(required date, evaluation date)) and/or
     * hours (hours_per_week >= minimum). Points = weight * passed checks / configured checks;
     * `unavailable` fails every check.
     */
    private function availability(PodWorkerRequirement $requirement, Profile $profile, CarbonImmutable $at): array
    {
        $status = $profile->availability_status;
        $requiredDate = $requirement->required_start_date?->format('Y-m-d');
        $minimumHours = (int) $requirement->minimum_hours_per_week;
        $availableOn = $this->availableOn($status, $profile, $at);
        $today = $at->format('Y-m-d');

        $checks = [];
        $startMet = null;
        $hoursMet = null;
        if ($requiredDate !== null) {
            $deadline = max($requiredDate, $today);
            $startMet = $status !== AvailabilityStatus::UNAVAILABLE && $availableOn !== null && $availableOn <= $deadline;
            $checks[] = $startMet;
        }
        if ($minimumHours > 0) {
            $hoursMet = $status !== AvailabilityStatus::UNAVAILABLE && $profile->hours_per_week !== null && (int) $profile->hours_per_week >= $minimumHours;
            $checks[] = $hoursMet;
        }

        $passed = count(array_filter($checks));
        $code = match (true) {
            $status === AvailabilityStatus::UNAVAILABLE => Code::AVAILABILITY_UNAVAILABLE,
            $passed === count($checks) => Code::AVAILABILITY_MET,
            $passed > 0 => Code::AVAILABILITY_PARTIAL,
            default => Code::AVAILABILITY_NOT_MET,
        };

        return [$passed, count($checks), $code, [
            'status' => $status?->value,
            'available_on' => $availableOn,
            'required_start_date' => $requiredDate,
            'start_met' => $startMet,
            'minimum_hours' => $minimumHours > 0 ? $minimumHours : null,
            'hours_per_week' => $profile->hours_per_week !== null ? (int) $profile->hours_per_week : null,
            'hours_met' => $hoursMet,
        ]];
    }

    /** Date (Y-m-d) the candidate can start, relative to the evaluation date; null if unknown/unavailable. */
    private function availableOn(?AvailabilityStatus $status, Profile $profile, CarbonImmutable $at): ?string
    {
        $today = $at->startOfDay();

        return match ($status) {
            AvailabilityStatus::IMMEDIATELY => $today->format('Y-m-d'),
            AvailabilityStatus::WITHIN_TWO_WEEKS => $today->addDays((int) config('pod_matching.availability.within_two_weeks_days', 14))->format('Y-m-d'),
            AvailabilityStatus::WITHIN_ONE_MONTH => $today->addMonthsNoOverflow((int) config('pod_matching.availability.within_one_month_months', 1))->format('Y-m-d'),
            AvailabilityStatus::SPECIFIC_DATE => $profile->available_from ? max($profile->available_from->format('Y-m-d'), $today->format('Y-m-d')) : null,
            AvailabilityStatus::UNAVAILABLE, null => null,
        };
    }

    /**
     * Configured checks: location (every comma-separated requirement token equals the candidate's
     * normalized city, state or country) and/or timezone (|candidate offset - required offset| at
     * the evaluation time <= tolerance hours * 60; a missing or invalid candidate timezone fails).
     * Points = weight * passed checks / configured checks.
     */
    private function location(PodWorkerRequirement $requirement, Profile $profile, CarbonImmutable $at): array
    {
        $tokens = LocationNormalizer::tokens($requirement->location);
        $candidateFields = array_values(array_unique(array_filter([
            LocationNormalizer::normalize($profile->city),
            LocationNormalizer::normalize($profile->state),
            LocationNormalizer::normalize($profile->country),
        ], fn (string $v) => $v !== '')));

        $checks = [];
        $context = [
            'required_location' => $requirement->location !== null ? trim((string) $requirement->location) : null,
            'candidate_location' => ['city' => $profile->city, 'state' => $profile->state, 'country' => $profile->country],
            'location_met' => null,
            'unmatched_tokens' => [],
            'required_timezone' => $requirement->timezone,
            'candidate_timezone' => $profile->timezone,
            'tolerance_hours' => null,
            'offset_difference_minutes' => null,
            'timezone_status' => null,
            'timezone_met' => null,
        ];

        if ($tokens !== []) {
            $unmatched = array_values(array_diff($tokens, $candidateFields));
            $context['location_met'] = $unmatched === [];
            $context['unmatched_tokens'] = $unmatched;
            $checks[] = $context['location_met'];
        }

        if (filled($requirement->timezone)) {
            $tolerance = (int) ($requirement->timezone_tolerance_hours ?? 0);
            $context['tolerance_hours'] = $tolerance;
            if (!LocationNormalizer::isValidTimezone($requirement->timezone)) {
                $context['timezone_status'] = 'requirement_invalid';
            } elseif (!filled($profile->timezone)) {
                $context['timezone_status'] = 'missing';
            } elseif (!LocationNormalizer::isValidTimezone($profile->timezone)) {
                $context['timezone_status'] = 'invalid';
            } else {
                $difference = abs(LocationNormalizer::offsetMinutes($profile->timezone, $at) - LocationNormalizer::offsetMinutes($requirement->timezone, $at));
                $context['offset_difference_minutes'] = $difference;
                $context['timezone_status'] = $difference <= $tolerance * 60 ? 'within_tolerance' : 'out_of_range';
            }
            $context['timezone_met'] = $context['timezone_status'] === 'within_tolerance';
            $checks[] = $context['timezone_met'];
        }

        $passed = count(array_filter($checks));
        $code = match (true) {
            $passed === count($checks) => Code::LOCATION_MET,
            $passed > 0 => Code::LOCATION_PARTIAL,
            default => Code::LOCATION_NOT_MET,
        };

        return [$passed, count($checks), $code, $context];
    }

    private function projectType(PodWorkerRequirement $requirement, Profile $profile): array
    {
        $required = $requirement->project_type->value;
        $candidate = $profile->projectTypes->map(fn ($t) => (string) ($t->project_type instanceof \BackedEnum ? $t->project_type->value : $t->project_type))
            ->unique()->sort()->values()->all();
        $matched = in_array($required, $candidate, true);

        return [$matched ? 1 : 0, 1, $matched ? Code::PROJECT_TYPE_MATCHED : Code::PROJECT_TYPE_NOT_MATCHED, [
            'required' => $required,
            'candidate' => $candidate,
        ]];
    }

    /** Compatible when the modes are equal or either side is flexible. Unweighted. */
    private function workMode(PodWorkerRequirement $requirement, Profile $profile): array
    {
        $required = $requirement->preferred_work_mode;
        $actual = $profile->preferred_work_mode;
        $compatible = $actual !== null && ($actual === $required || $actual === WorkMode::FLEXIBLE || $required === WorkMode::FLEXIBLE);

        return [$compatible ? 1 : 0, 1, $compatible ? Code::WORK_MODE_COMPATIBLE : Code::WORK_MODE_INCOMPATIBLE, [
            'required' => $required->value,
            'actual' => $actual?->value,
        ]];
    }

    /** Compatible only when the engagement types are equal. Unweighted. */
    private function engagement(PodWorkerRequirement $requirement, Profile $profile): array
    {
        $required = $requirement->preferred_engagement_type;
        $actual = $profile->preferred_engagement_type;
        $compatible = $actual !== null && $actual === $required;

        return [$compatible ? 1 : 0, 1, $compatible ? Code::ENGAGEMENT_COMPATIBLE : Code::ENGAGEMENT_INCOMPATIBLE, [
            'required' => $required->value,
            'actual' => $actual?->value,
        ]];
    }

    // ------------------------------------------------------------------ helpers

    /** Required skills ordered by id, so results never depend on load order. */
    private function requiredSkills(PodWorkerRequirement $requirement)
    {
        return $requirement->skills->sortBy('id')->values();
    }

    private function jobTitle(PodWorkerRequirement $requirement): array
    {
        return ['id' => (int) $requirement->job_title_id, 'title' => $requirement->jobTitle?->title];
    }

    private function assertLoaded(PodWorkerRequirement $requirement, User $candidate): void
    {
        foreach (self::REQUIREMENT_RELATIONS as $relation) {
            if (!$requirement->relationLoaded($relation)) {
                throw new \LogicException("PodMatchScorer needs the requirement's {$relation} relation loaded.");
            }
        }
        if (!$candidate->relationLoaded('skills') || !$candidate->relationLoaded('profile')) {
            throw new \LogicException('PodMatchScorer needs the candidate skills and profile loaded.');
        }
        foreach (['experiences', 'preferredRoles', 'projectTypes'] as $relation) {
            if (!$candidate->profile->relationLoaded($relation)) {
                throw new \LogicException("PodMatchScorer needs the profile {$relation} relation loaded.");
            }
        }
    }

    /** A decimal(…,1) value as an integer number of tenths. */
    private static function tenths(mixed $value): int
    {
        return (int) round(((float) $value) * 10);
    }

    private static function formatTenths(int $tenths): string
    {
        return intdiv($tenths, 10) . '.' . ($tenths % 10);
    }

    /** Days since the Unix epoch for a Y-m-d date (calendar arithmetic only, timezone-free). */
    private static function dayNumber(string $date): int
    {
        return intdiv(CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC')->getTimestamp(), 86400);
    }

    /** Total days covered by half-open [start, end) intervals, overlaps counted once. */
    private static function unionDays(array $intervals): int
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $total = 0;
        [$start, $end] = array_shift($intervals);
        foreach ($intervals as [$s, $e]) {
            if ($s <= $end) {
                $end = max($end, $e);
                continue;
            }
            $total += $end - $start;
            [$start, $end] = [$s, $e];
        }

        return $total + ($end - $start);
    }
}

<?php

namespace Tests\Feature\Matching;

use App\Enums\Matching\MatchCriterion as C;
use App\Enums\Matching\MatchReasonCode as Code;
use App\Enums\Matching\MatchStatus;
use App\Enums\Profile\SkillProficiency;
use Illuminate\Support\Facades\DB;

/** UNA-013: PodMatchScorer formulas, one criterion at a time, at a fixed evaluation time. */
class PodMatchScorerTest extends MatchingTestCase
{
    public function test_a_perfect_match_scores_100_and_is_eligible(): void
    {
        $result = $this->score($this->fullRequirement(['mandatory_criteria' => C::values()]), $this->perfectCandidate());

        $this->assertSame(['100.00', MatchStatus::ELIGIBLE, 100, []], [$result->score(), $result->status, $result->activeWeight, $result->failedMandatory]);
        $this->assertTrue($result->isEligible());
        $this->assertSame(config('pod_matching.version'), $result->scoringVersion);
        $this->assertSame(C::cases(), array_map(fn ($c) => $c->criterion, $result->criteria), 'every criterion, in declaration order');
        foreach ($result->criteria as $criterion) {
            $this->assertTrue($criterion->active && $criterion->passed, $criterion->criterion->value);
            $this->assertSame($criterion->possiblePoints * 100, $criterion->awardedHundredths);
        }
        $this->assertSame(100, array_sum(config('pod_matching.weights')));
    }

    public function test_the_scorer_is_pure_and_refuses_unloaded_relations(): void
    {
        $requirement = $this->fullRequirement()->load(\App\Services\Matching\PodMatchScorer::REQUIREMENT_RELATIONS);
        $candidate = $this->perfectCandidate()->load(\App\Services\Matching\PodMatchScorer::CANDIDATE_RELATIONS);

        DB::enableQueryLog();
        $first = $this->scorer()->score($requirement, $candidate, $this->at());
        $second = $this->scorer()->score($requirement, $candidate, $this->at());
        $this->assertSame([], DB::getQueryLog(), 'no queries once the declared relations are loaded');
        DB::disableQueryLog();
        $this->assertEquals($first, $second, 'deterministic');

        $this->expectException(\LogicException::class);
        $this->scorer()->score($this->fullRequirement(), $candidate, $this->at());
    }

    public function test_partial_skill_coverage_reduces_the_score_proportionally(): void
    {
        $requirement = $this->requirement([], [$this->php->id, $this->sql->id, $this->docker->id, $this->laravel->id]);

        foreach ([[[], '0.00'], [[$this->php->id], '25.00'], [[$this->php->id, $this->sql->id, $this->docker->id], '75.00']] as [$held, $expected]) {
            $result = $this->score($requirement, $this->candidate([], array_fill_keys($held, 'expert')));
            $this->assertSame($expected, $result->score());
            $this->assertSame(25, $result->activeWeight, 'only skill coverage is configured');
        }

        $coverage = $this->score($requirement, $this->candidate([], [$this->php->id => null, $this->docker->id => null]))->criterion(C::SKILL_COVERAGE);
        $this->assertSame([Code::SKILLS_SOME_MISSING, 1250, false], [$coverage->code, $coverage->awardedHundredths, $coverage->passed]);
        $this->assertSame([['id' => $this->php->id, 'name' => 'PHP'], ['id' => $this->docker->id, 'name' => 'Docker']], $coverage->context['matched']);
        $this->assertSame([['id' => $this->sql->id, 'name' => 'SQL'], ['id' => $this->laravel->id, 'name' => 'Laravel']], $coverage->context['missing']);
    }

    public function test_proficiency_is_an_ordered_scale_scored_by_attainment(): void
    {
        $this->assertSame(['beginner', 'intermediate', 'advanced', 'expert'],
            collect(SkillProficiency::cases())->sortBy(fn ($p) => $p->rank())->map->value->values()->all());

        $requirement = $this->requirement(['minimum_skill_proficiency' => 'advanced'], [$this->php->id]);
        $expected = ['beginner' => 500, 'intermediate' => 1000, 'advanced' => 1500, 'expert' => 1500];
        foreach ($expected as $level => $hundredths) {
            $proficiency = $this->score($requirement, $this->candidate([], [$this->php->id => $level]))->criterion(C::SKILL_PROFICIENCY);
            $this->assertSame($hundredths, $proficiency->awardedHundredths, $level);
            $this->assertSame(in_array($level, ['advanced', 'expert'], true), $proficiency->passed, $level);
            $this->assertSame(['required' => 'advanced', 'actual' => $level], ['required' => $proficiency->context['required'], 'actual' => $proficiency->context['skills'][0]['actual']]);
        }

        // Averaged over the required skills: expert (3/3) + intermediate (2/3) + missing (0/3) = 5/9.
        $three = $this->requirement(['minimum_skill_proficiency' => 'advanced'], [$this->php->id, $this->sql->id, $this->docker->id]);
        $proficiency = $this->score($three, $this->candidate([], [$this->php->id => 'expert', $this->sql->id => 'intermediate']))->criterion(C::SKILL_PROFICIENCY);
        $this->assertSame([833, Code::PROFICIENCY_PARTIAL, 1], [$proficiency->awardedHundredths, $proficiency->code, $proficiency->context['met_count']]);
    }

    public function test_a_null_legacy_proficiency_never_meets_a_configured_minimum(): void
    {
        $requirement = $this->requirement(['minimum_skill_proficiency' => 'beginner'], [$this->php->id]);
        $result = $this->score($requirement, $this->candidate([], [$this->php->id => null]));
        $proficiency = $result->criterion(C::SKILL_PROFICIENCY);

        $this->assertSame([0, false, Code::PROFICIENCY_NOT_MET], [$proficiency->awardedHundredths, $proficiency->passed, $proficiency->code]);
        $this->assertSame(['id' => $this->php->id, 'name' => 'PHP', 'held' => true, 'actual' => null, 'met' => false], $proficiency->context['skills'][0]);
        $this->assertTrue($result->criterion(C::SKILL_COVERAGE)->passed, 'the skill itself still counts for coverage');
    }

    public function test_only_experience_in_the_required_role_counts(): void
    {
        $requirement = $this->requirement(['job_title_id' => $this->role->id, 'minimum_years_experience' => 2]);
        $candidate = $this->candidate(['job_title_id' => $this->role->id, 'years_experience' => 12], [], [
            ['job_title_id' => $this->designer->id, 'start_date' => '2010-01-01', 'end_date' => '2020-01-01'],
            ['job_title_id' => $this->role->id, 'start_date' => '2025-06-15', 'is_current' => true],
        ]);
        $experience = $this->score($requirement, $candidate)->criterion(C::EXPERIENCE);

        // 365 days of 730.5 required: 15 * 365000 / 730500 = 7.49 points.
        $this->assertSame([365, '1.0', 'structured', false, 749, Code::EXPERIENCE_PARTIAL],
            [$experience->context['relevant_days'], $experience->context['relevant_years'], $experience->context['source'], $experience->context['fallback_used'], $experience->awardedHundredths, $experience->code]);
    }

    public function test_overlapping_experience_is_not_double_counted_and_is_capped_at_the_minimum(): void
    {
        $requirement = $this->requirement(['job_title_id' => $this->role->id, 'minimum_years_experience' => 1]);
        $candidate = $this->candidate([], [], [
            ['job_title_id' => $this->role->id, 'start_date' => '2024-01-01', 'end_date' => '2025-01-01'],
            ['job_title_id' => $this->role->id, 'start_date' => '2024-07-01', 'end_date' => '2025-07-01'], // overlaps the first
            ['job_title_id' => $this->role->id, 'start_date' => '2024-03-01', 'end_date' => '2024-04-01'], // inside the first
            ['job_title_id' => $this->role->id, 'start_date' => '2019-01-01'], // ended, no end date: not measurable
        ]);
        $experience = $this->score($requirement, $candidate)->criterion(C::EXPERIENCE);

        // 2024-01-01 .. 2025-07-01 = 547 days, not 366 + 365 + 31.
        $this->assertSame([547, '1.5', 1, Code::EXPERIENCE_MET], [$experience->context['relevant_days'], $experience->context['relevant_years'], $experience->context['skipped_count'], $experience->code]);
        $this->assertSame(1500, $experience->awardedHundredths);

        // Current roles end at the evaluation date; a future end date is clipped to it.
        $clipped = $this->candidate([], [], [['job_title_id' => $this->role->id, 'start_date' => '2026-01-01', 'end_date' => '2027-01-01']]);
        $this->assertSame(165, $this->score($requirement, $clipped)->criterion(C::EXPERIENCE)->context['relevant_days']);

        $veteran = $this->candidate([], [], [['job_title_id' => $this->role->id, 'start_date' => '2000-01-01', 'is_current' => true]]);
        $this->assertSame(1500, $this->score($requirement, $veteran)->criterion(C::EXPERIENCE)->awardedHundredths, 'never more than the weight');
    }

    public function test_aggregate_experience_is_a_fallback_only_for_a_matching_primary_role(): void
    {
        $requirement = $this->requirement(['job_title_id' => $this->role->id, 'minimum_years_experience' => 4]);

        $fallback = $this->score($requirement, $this->candidate(['job_title_id' => $this->role->id, 'years_experience' => 3]))->criterion(C::EXPERIENCE);
        $this->assertSame(['aggregate_fallback', true, '3.0', 1125], [$fallback->context['source'], $fallback->context['fallback_used'], $fallback->context['relevant_years'], $fallback->awardedHundredths]);

        $otherRole = $this->score($requirement, $this->candidate(['job_title_id' => $this->designer->id, 'years_experience' => 20]))->criterion(C::EXPERIENCE);
        $this->assertSame(['none', false, 0, Code::EXPERIENCE_NONE], [$otherRole->context['source'], $otherRole->context['fallback_used'], $otherRole->awardedHundredths, $otherRole->code]);

        // Structured experience in the role wins over the aggregate, even when the aggregate is higher.
        $structured = $this->score($requirement, $this->candidate(['job_title_id' => $this->role->id, 'years_experience' => 20], [], [
            ['job_title_id' => $this->role->id, 'start_date' => '2025-06-15', 'end_date' => '2026-06-15'],
        ]))->criterion(C::EXPERIENCE);
        $this->assertSame(['structured', false, '1.0'], [$structured->context['source'], $structured->context['fallback_used'], $structured->context['relevant_years']]);
    }

    public function test_role_matches_the_primary_or_a_preferred_role_but_not_history_alone(): void
    {
        $requirement = $this->requirement(['job_title_id' => $this->role->id]);

        $cases = [
            [$this->candidate(['job_title_id' => $this->role->id]), Code::ROLE_PRIMARY, true],
            [$this->candidate(['job_title_id' => $this->designer->id], [], [], [$this->role->id]), Code::ROLE_PREFERRED, true],
            [$this->candidate(['job_title_id' => $this->designer->id], [], [['job_title_id' => $this->role->id, 'start_date' => '2020-01-01', 'end_date' => '2021-01-01']]), Code::ROLE_HISTORICAL_ONLY, false],
            [$this->candidate(['job_title_id' => $this->designer->id]), Code::ROLE_MISMATCH, false],
        ];
        foreach ($cases as [$candidate, $code, $passed]) {
            $role = $this->score($requirement, $candidate)->criterion(C::ROLE);
            $this->assertSame([$code, $passed, $passed ? 1500 : 0], [$role->code, $role->passed, $role->awardedHundredths]);
        }
    }

    public function test_availability_resolves_dates_from_the_evaluation_time_and_checks_hours(): void
    {
        $requirement = $this->requirement(['required_start_date' => '2026-07-01', 'minimum_hours_per_week' => 30]);
        $cases = [
            // status, available_from, hours => [awarded hundredths, code, available_on]
            [['within_two_weeks', null, 40], [1000, Code::AVAILABILITY_MET, '2026-06-29']],
            [['within_one_month', null, 40], [500, Code::AVAILABILITY_PARTIAL, '2026-07-15']],
            [['specific_date', '2026-07-01', 30], [1000, Code::AVAILABILITY_MET, '2026-07-01']],
            [['specific_date', '2026-07-02', 30], [500, Code::AVAILABILITY_PARTIAL, '2026-07-02']],
            [['specific_date', null, 30], [500, Code::AVAILABILITY_PARTIAL, null]],
            [['immediately', null, 20], [500, Code::AVAILABILITY_PARTIAL, '2026-06-15']],
            [['immediately', null, null], [500, Code::AVAILABILITY_PARTIAL, '2026-06-15']],
            [[null, null, null], [0, Code::AVAILABILITY_NOT_MET, null]],
        ];
        foreach ($cases as [[$status, $from, $hours], [$awarded, $code, $on]]) {
            $availability = $this->score($requirement, $this->candidate(['availability_status' => $status, 'available_from' => $from, 'hours_per_week' => $hours]))->criterion(C::AVAILABILITY);
            $this->assertSame([$awarded, $code, $on], [$availability->awardedHundredths, $availability->code, $availability->context['available_on']], "{$status} {$from} {$hours}");
        }

        // A start date already in the past means "as soon as possible".
        $past = $this->requirement(['required_start_date' => '2026-06-01']);
        $this->assertTrue($this->score($past, $this->candidate(['availability_status' => 'immediately']))->criterion(C::AVAILABILITY)->passed);
        $this->assertFalse($this->score($past, $this->candidate(['availability_status' => 'within_two_weeks']))->criterion(C::AVAILABILITY)->passed);
    }

    public function test_unavailable_fails_availability(): void
    {
        $requirement = $this->requirement(['required_start_date' => '2026-07-01', 'minimum_hours_per_week' => 10]);
        $availability = $this->score($requirement, $this->candidate(['availability_status' => 'unavailable', 'available_from' => '2026-06-01', 'hours_per_week' => 40]))->criterion(C::AVAILABILITY);

        $this->assertSame([0, false, Code::AVAILABILITY_UNAVAILABLE, false, false], [
            $availability->awardedHundredths, $availability->passed, $availability->code, $availability->context['start_met'], $availability->context['hours_met'],
        ]);
    }

    public function test_location_tokens_and_timezone_offsets_with_tolerance(): void
    {
        $location = $this->requirement(['location' => ' colombo ,  SRI-LANKA ']);
        $this->assertTrue($this->score($location, $this->candidate(['city' => 'Colombo', 'country' => 'Sri Lanka']))->criterion(C::LOCATION)->passed);
        $partial = $this->score($location, $this->candidate(['city' => 'Kandy', 'country' => 'Sri Lanka']))->criterion(C::LOCATION);
        $this->assertSame([false, ['colombo']], [$partial->passed, $partial->context['unmatched_tokens']]);

        // Asia/Colombo is +05:30. Asia/Kolkata +05:30, Asia/Dubai +04:00 (90 minutes away).
        $timezone = fn (?int $tolerance) => $this->requirement(['timezone' => 'Asia/Colombo', 'timezone_tolerance_hours' => $tolerance]);
        $check = fn ($requirement, string $tz) => $this->score($requirement, $this->candidate(['timezone' => $tz]))->criterion(C::LOCATION);

        $this->assertSame(['within_tolerance', 0], [$check($timezone(null), 'Asia/Kolkata')->context['timezone_status'], $check($timezone(null), 'Asia/Kolkata')->context['offset_difference_minutes']]);
        $this->assertSame(['out_of_range', 90, false], [$check($timezone(1), 'Asia/Dubai')->context['timezone_status'], $check($timezone(1), 'Asia/Dubai')->context['offset_difference_minutes'], $check($timezone(1), 'Asia/Dubai')->passed]);
        $this->assertTrue($check($timezone(2), 'Asia/Dubai')->passed);

        // Offsets are taken at the evaluation time: London is UTC+1 in June.
        $utc = $this->requirement(['timezone' => 'UTC', 'timezone_tolerance_hours' => 0]);
        $this->assertSame([60, false], [$check($utc, 'Europe/London')->context['offset_difference_minutes'], $check($utc, 'Europe/London')->passed]);

        // Both configured: one check each, half the points when only one passes.
        $both = $this->requirement(['location' => 'Sri Lanka', 'timezone' => 'Asia/Colombo']);
        $result = $this->score($both, $this->candidate(['country' => 'Sri Lanka', 'timezone' => 'Asia/Dubai']))->criterion(C::LOCATION);
        $this->assertSame([500, Code::LOCATION_PARTIAL, true, false], [$result->awardedHundredths, $result->code, $result->context['location_met'], $result->context['timezone_met']]);
    }

    public function test_a_missing_or_invalid_timezone_cannot_pass_a_mandatory_timezone_requirement(): void
    {
        $requirement = $this->requirement(['timezone' => 'Asia/Colombo', 'timezone_tolerance_hours' => 14, 'mandatory_criteria' => ['location']]);

        foreach ([null => 'missing', 'Mars/Olympus' => 'invalid', '+05:30' => 'invalid', 'IST' => 'invalid'] as $tz => $status) {
            $result = $this->score($requirement, $this->candidate(['timezone' => $tz === '' ? null : $tz]));
            $this->assertSame([$status, false, MatchStatus::DISQUALIFIED, [C::LOCATION]],
                [$result->criterion(C::LOCATION)->context['timezone_status'], $result->criterion(C::LOCATION)->passed, $result->status, $result->failedMandatory], (string) $tz);
        }
        $this->assertTrue($this->score($requirement, $this->candidate(['timezone' => 'Asia/Calcutta']))->isEligible(), 'IANA backward-compatible alias');
    }

    public function test_project_type_matches_the_exact_enum(): void
    {
        $requirement = $this->requirement(['project_type' => 'long_term']);

        $matched = $this->score($requirement, $this->candidate([], [], [], [], ['short_term', 'long_term']))->criterion(C::PROJECT_TYPE);
        $this->assertSame([1000, Code::PROJECT_TYPE_MATCHED, ['long_term', 'short_term']], [$matched->awardedHundredths, $matched->code, $matched->context['candidate']]);

        $missed = $this->score($requirement, $this->candidate([], [], [], [], ['ongoing']))->criterion(C::PROJECT_TYPE);
        $this->assertSame([0, Code::PROJECT_TYPE_NOT_MATCHED], [$missed->awardedHundredths, $missed->code]);
    }

    public function test_inactive_criteria_are_excluded_and_active_weights_renormalize(): void
    {
        $requirement = $this->requirement(['job_title_id' => $this->role->id], [$this->php->id, $this->sql->id]);

        $perfect = $this->score($requirement, $this->candidate(['job_title_id' => $this->role->id], [$this->php->id => null, $this->sql->id => null]));
        $this->assertSame(['100.00', 40, MatchStatus::ELIGIBLE], [$perfect->score(), $perfect->activeWeight, $perfect->status]);

        // (12.50 + 15.00) / 40 = 68.75
        $half = $this->score($requirement, $this->candidate(['job_title_id' => $this->role->id], [$this->php->id => null]));
        $this->assertSame('68.75', $half->score());

        $inactive = array_values(array_filter($half->criteria, fn ($c) => !$c->active));
        $this->assertSame([C::SKILL_PROFICIENCY, C::EXPERIENCE, C::AVAILABILITY, C::LOCATION, C::PROJECT_TYPE, C::WORK_MODE, C::ENGAGEMENT_TYPE], array_map(fn ($c) => $c->criterion, $inactive));
        foreach ($inactive as $criterion) {
            $this->assertSame([Code::NOT_CONFIGURED, 0, false], [$criterion->code, $criterion->awardedHundredths, $criterion->passed]);
        }
    }

    public function test_no_weighted_criteria_is_a_deterministic_zero_score_that_is_not_eligible(): void
    {
        // Work mode and engagement are compatibility checks only: nothing to score.
        $requirement = $this->requirement(['preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract']);
        $result = $this->score($requirement, $this->candidate(['preferred_work_mode' => 'flexible', 'preferred_engagement_type' => 'contract']));

        $this->assertSame(['0.00', 0, MatchStatus::NOT_SCORABLE, false], [$result->score(), $result->activeWeight, $result->status, $result->isEligible()]);
        $this->assertSame([Code::WORK_MODE_COMPATIBLE, Code::ENGAGEMENT_COMPATIBLE], [$result->criterion(C::WORK_MODE)->code, $result->criterion(C::ENGAGEMENT_TYPE)->code]);
    }

    public function test_compatibility_checks_never_change_the_score(): void
    {
        $requirement = $this->requirement(['job_title_id' => $this->role->id, 'preferred_work_mode' => 'onsite', 'preferred_engagement_type' => 'full_time']);
        $result = $this->score($requirement, $this->candidate(['job_title_id' => $this->role->id, 'preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract']));

        $this->assertSame(['100.00', MatchStatus::ELIGIBLE], [$result->score(), $result->status]);
        $this->assertSame([false, 0, 0], [$result->criterion(C::WORK_MODE)->passed, $result->criterion(C::WORK_MODE)->possiblePoints, $result->criterion(C::WORK_MODE)->awardedHundredths]);
    }

    /** The worked example in docs/pod-matching.md, number for number. */
    public function test_documented_worked_example_mandatory_failure_keeps_the_score(): void
    {
        $requirement = $this->requirement([
            'job_title_id' => $this->role->id,
            'minimum_skill_proficiency' => 'advanced',
            'minimum_years_experience' => 4,
            'required_start_date' => '2026-07-01',
            'minimum_hours_per_week' => 30,
            'location' => 'Sri Lanka',
            'preferred_work_mode' => 'remote',
            'preferred_engagement_type' => 'contract',
            'mandatory_criteria' => ['availability'],
        ], [$this->php->id, $this->sql->id, $this->docker->id]);
        $candidate = $this->candidate([
            'job_title_id' => $this->role->id,
            'availability_status' => 'within_one_month',
            'hours_per_week' => 40,
            'city' => 'Kandy',
            'country' => 'Sri Lanka',
            'preferred_work_mode' => 'remote',
            'preferred_engagement_type' => 'full_time',
        ], [$this->php->id => 'expert', $this->sql->id => 'intermediate'], [
            ['job_title_id' => $this->role->id, 'start_date' => '2022-01-01', 'end_date' => '2023-12-31'],
            ['job_title_id' => $this->role->id, 'start_date' => '2023-07-01', 'is_current' => true],
        ]);

        $result = $this->score($requirement, $candidate);

        $this->assertSame([
            'skill_coverage' => '16.67', 'skill_proficiency' => '8.33', 'experience' => '15.00', 'role' => '15.00',
            'availability' => '5.00', 'location' => '10.00', 'project_type' => '0.00', 'work_mode' => '0.00', 'engagement_type' => '0.00',
        ], collect($result->criteria)->mapWithKeys(fn ($c) => [$c->criterion->value => $c->awardedPoints()])->all());
        $this->assertSame(1626, $result->criterion(C::EXPERIENCE)->context['relevant_days']);
        $this->assertSame(['77.78', 90, MatchStatus::DISQUALIFIED, [C::AVAILABILITY]], [$result->score(), $result->activeWeight, $result->status, $result->failedMandatory]);
        $this->assertTrue($result->criterion(C::AVAILABILITY)->disqualifying);
        $this->assertFalse($result->criterion(C::ENGAGEMENT_TYPE)->disqualifying, 'failed but not mandatory');
    }

    public function test_every_failed_mandatory_criterion_is_retained(): void
    {
        $requirement = $this->fullRequirement(['mandatory_criteria' => ['skill_coverage', 'role', 'location', 'project_type', 'work_mode']]);
        $candidate = $this->perfectCandidate(['job_title_id' => $this->designer->id, 'country' => 'India', 'preferred_work_mode' => 'onsite']);

        $result = $this->score($requirement, $candidate);

        $this->assertSame([C::ROLE, C::LOCATION, C::WORK_MODE], $result->failedMandatory);
        $this->assertSame(MatchStatus::DISQUALIFIED, $result->status);
        $this->assertGreaterThan(0, $result->scoreHundredths, 'score is kept for diagnostics');
        $this->assertSame(['role', 'location', 'work_mode'], collect($result->criteria)->filter->disqualifying->map(fn ($c) => $c->criterion->value)->values()->all());
    }
}

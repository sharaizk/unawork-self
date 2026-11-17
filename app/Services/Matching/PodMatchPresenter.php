<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchReasonCode as Code;
use App\Enums\Matching\MatchStatus;
use App\Enums\Profile\AvailabilityStatus;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\ProjectType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Models\PodMatch;
use App\Models\PodMatchReason;

/**
 * Read model for a persisted match (UNA-013): turns stored codes + structured context into
 * translated, ordered, display-ready entries for Blade or JSON (PodMatchResource). Nothing displayed
 * comes from stored prose or exception messages.
 */
class PodMatchPresenter
{
    /**
     * @return array{score:string, threshold:int, qualified:bool, qualification:array{code:string, label:string}, status:string, status_label:string, eligible:bool, summary:string,
     *     scoring_version:string, evaluated_at:?string, failed_mandatory: array<int, array{criterion:string, label:string}>,
     *     criteria: array<int, array<string, mixed>>}
     */
    public function present(PodMatch $match): array
    {
        $reasons = $match->relationLoaded('reasons') ? $match->reasons : $match->reasons()->get();
        $criteria = $reasons->sortBy([['position', 'asc'], ['id', 'asc']])->values()->map(fn (PodMatchReason $r) => $this->criterion($r))->all();
        $match->loadMissing('requirement.pod');
        $qualification = MatchQualification::of($match); // UNA-014: the POD's current threshold

        return [
            'score' => (string) $match->score,
            'threshold' => $qualification->threshold,
            'qualified' => $qualification->qualified,
            'qualification' => ['code' => $qualification->reason->value, 'label' => $qualification->reason->label()],
            'status' => $match->status->value,
            'status_label' => $match->status->label(),
            'eligible' => $match->isEligible(),
            'summary' => $this->summary($match, $reasons),
            'scoring_version' => (string) $match->scoring_version,
            'evaluated_at' => $match->evaluated_at?->toIso8601String(),
            // UNA-016 names for the same metadata (evaluated_at / scoring_version kept for compatibility).
            'algorithm' => (string) $match->algorithm,
            'algorithm_version' => (string) $match->scoring_version,
            'generated_at' => $match->evaluated_at?->toIso8601String(),
            'failed_mandatory' => $reasons->where('disqualifying', true)->sortBy('position')->values()
                ->map(fn (PodMatchReason $r) => ['criterion' => $r->criterion->value, 'label' => $r->criterion->label()])->all(),
            'criteria' => $criteria,
        ];
    }

    /** @return array<string, mixed> one ordered, display-ready criterion entry */
    public function criterion(PodMatchReason $reason): array
    {
        $context = (array) ($reason->context ?? []);

        return [
            'criterion' => $reason->criterion->value,
            'label' => $reason->criterion->label(),
            'active' => $reason->active,
            'passed' => $reason->passed,
            'mandatory' => $reason->mandatory,
            'disqualifying' => $reason->disqualifying,
            'weighted' => $reason->criterion->isWeighted(),
            'awarded_points' => (string) $reason->awarded_points,
            'possible_points' => $reason->possible_points,
            'code' => $reason->code->value,
            'position' => $reason->position,
            'explanation' => $this->explanation($reason->code, $context),
            // UNA-017: short positive phrase for a passed criterion (reason chips), null otherwise.
            'highlight' => $reason->active && $reason->passed ? $this->highlight($reason->code) : null,
            'details' => $context,
        ];
    }

    private function summary(PodMatch $match, $reasons): string
    {
        return match ($match->status) {
            MatchStatus::ELIGIBLE => __('Eligible with a match score of :score.', ['score' => $match->score]),
            MatchStatus::DISQUALIFIED => __('Not eligible: mandatory criteria not met (:criteria). Match score :score.', [
                'criteria' => $reasons->where('disqualifying', true)->sortBy('position')->map(fn ($r) => $r->criterion->label())->implode(', '),
                'score' => $match->score,
            ]),
            MatchStatus::NOT_SCORABLE => __('Not scorable: this worker requirement configures no scored criteria.'),
        };
    }

    private function explanation(Code $code, array $c): string
    {
        $names = fn (string $key) => implode(', ', array_map(fn ($s) => (string) ($s['name'] ?? ''), (array) ($c[$key] ?? [])));
        $proficiency = fn ($v) => SkillProficiency::tryFrom((string) $v)?->label() ?? __('Not set');
        $role = (string) ($c['job_title']['title'] ?? __('the required role'));

        return match ($code) {
            Code::NOT_CONFIGURED => __('Not required by this worker requirement; not scored.'),

            Code::SKILLS_ALL_MATCHED => __('Has all :count required skills.', ['count' => $c['required_count'] ?? 0]),
            Code::SKILLS_SOME_MISSING => __('Has :matched of :count required skills. Missing: :missing.', ['matched' => $c['matched_count'] ?? 0, 'count' => $c['required_count'] ?? 0, 'missing' => $names('missing')]),
            Code::SKILLS_NONE_MATCHED => __('Has none of the required skills. Missing: :missing.', ['missing' => $names('missing')]),

            Code::PROFICIENCY_MET => __('Meets the minimum proficiency (:level) in every required skill.', ['level' => $proficiency($c['required'] ?? null)]),
            Code::PROFICIENCY_PARTIAL, Code::PROFICIENCY_NOT_MET => __('Meets the minimum proficiency (:level) in :met of :count required skills. Below the minimum: :skills.', [
                'level' => $proficiency($c['required'] ?? null),
                'met' => $c['met_count'] ?? 0,
                'count' => $c['required_count'] ?? 0,
                'skills' => implode(', ', array_map(
                    fn ($s) => ($s['name'] ?? '') . ' (' . (($s['held'] ?? false) ? $proficiency($s['actual'] ?? null) : __('skill missing')) . ')',
                    array_values(array_filter((array) ($c['skills'] ?? []), fn ($s) => !($s['met'] ?? false))),
                )),
            ]),

            Code::EXPERIENCE_MET, Code::EXPERIENCE_PARTIAL, Code::EXPERIENCE_NONE => ($c['fallback_used'] ?? false)
                ? __(':years of :required required years as :role (from the total years of experience on the profile).', ['years' => $c['relevant_years'] ?? '0.0', 'required' => $c['required_years'] ?? '0.0', 'role' => $role])
                : __(':years of :required required years of experience as :role.', ['years' => $c['relevant_years'] ?? '0.0', 'required' => $c['required_years'] ?? '0.0', 'role' => $role]),

            Code::ROLE_PRIMARY => __(':role is the primary role.', ['role' => $role]),
            Code::ROLE_PREFERRED => __(':role is one of the preferred roles.', ['role' => $role]),
            Code::ROLE_HISTORICAL_ONLY => __('Has worked as :role before, but it is not the primary or a preferred role.', ['role' => $role]),
            Code::ROLE_MISMATCH => __(':role is not the primary or a preferred role.', ['role' => $role]),

            Code::AVAILABILITY_UNAVAILABLE => __('Not available.'),
            Code::AVAILABILITY_MET, Code::AVAILABILITY_PARTIAL, Code::AVAILABILITY_NOT_MET => $this->availabilityText($c),

            Code::LOCATION_MET, Code::LOCATION_PARTIAL, Code::LOCATION_NOT_MET => $this->locationText($c),

            Code::PROJECT_TYPE_MATCHED => __('Prefers :type projects.', ['type' => ProjectType::tryFrom((string) ($c['required'] ?? ''))?->label() ?? '']),
            Code::PROJECT_TYPE_NOT_MATCHED => __('Does not list :type among preferred project types.', ['type' => ProjectType::tryFrom((string) ($c['required'] ?? ''))?->label() ?? '']),

            Code::WORK_MODE_COMPATIBLE => __('Work mode is compatible (:actual for :required).', ['actual' => WorkMode::tryFrom((string) ($c['actual'] ?? ''))?->label() ?? '', 'required' => WorkMode::tryFrom((string) ($c['required'] ?? ''))?->label() ?? '']),
            Code::WORK_MODE_INCOMPATIBLE => __('Work mode is not compatible (:actual for :required).', ['actual' => WorkMode::tryFrom((string) ($c['actual'] ?? ''))?->label() ?? __('Not set'), 'required' => WorkMode::tryFrom((string) ($c['required'] ?? ''))?->label() ?? '']),
            Code::ENGAGEMENT_COMPATIBLE => __('Engagement type matches (:required).', ['required' => EngagementType::tryFrom((string) ($c['required'] ?? ''))?->label() ?? '']),
            Code::ENGAGEMENT_INCOMPATIBLE => __('Engagement type does not match (:actual for :required).', ['actual' => EngagementType::tryFrom((string) ($c['actual'] ?? ''))?->label() ?? __('Not set'), 'required' => EngagementType::tryFrom((string) ($c['required'] ?? ''))?->label() ?? '']),
        };
    }

    /** Short, supportive phrase for a passed criterion (UNA-017 recommendation chips). */
    public function highlight(Code $code): ?string
    {
        return match ($code) {
            Code::SKILLS_ALL_MATCHED => __('All required skills matched'),
            Code::PROFICIENCY_MET => __('Skill level meets the requirement'),
            Code::EXPERIENCE_MET => __('Enough relevant experience'),
            Code::ROLE_PRIMARY => __('Matches your primary role'),
            Code::ROLE_PREFERRED => __('One of your preferred roles'),
            Code::AVAILABILITY_MET => __('Availability aligned'),
            Code::LOCATION_MET => __('Location and timezone compatible'),
            Code::PROJECT_TYPE_MATCHED => __('Your preferred project type'),
            Code::WORK_MODE_COMPATIBLE => __('Work mode compatible'),
            Code::ENGAGEMENT_COMPATIBLE => __('Engagement type matches'),
            default => null,
        };
    }

    private function availabilityText(array $c): string
    {
        $parts = [];
        if (($c['start_met'] ?? null) !== null) {
            $status = AvailabilityStatus::tryFrom((string) ($c['status'] ?? ''));
            $parts[] = $c['start_met']
                ? __('Can start by :date (:status).', ['date' => $c['required_start_date'], 'status' => $status?->label() ?? ''])
                : (($c['available_on'] ?? null)
                    ? __('Can start on :available, after :date.', ['available' => $c['available_on'], 'date' => $c['required_start_date']])
                    : __('Start date unknown; required by :date.', ['date' => $c['required_start_date']]));
        }
        if (($c['hours_met'] ?? null) !== null) {
            $parts[] = $c['hours_met']
                ? __(':hours hours per week available (minimum :minimum).', ['hours' => $c['hours_per_week'], 'minimum' => $c['minimum_hours']])
                : (($c['hours_per_week'] ?? null) === null
                    ? __('Weekly hours not set (minimum :minimum).', ['minimum' => $c['minimum_hours']])
                    : __('Only :hours hours per week available (minimum :minimum).', ['hours' => $c['hours_per_week'], 'minimum' => $c['minimum_hours']]));
        }

        return implode(' ', $parts);
    }

    private function locationText(array $c): string
    {
        $parts = [];
        if (($c['location_met'] ?? null) !== null) {
            $parts[] = $c['location_met']
                ? __('Located in :location.', ['location' => $c['required_location']])
                : __('Not located in :location.', ['location' => $c['required_location']]);
        }
        if (($c['timezone_met'] ?? null) !== null) {
            $parts[] = match ($c['timezone_status'] ?? null) {
                'within_tolerance' => __('Timezone is within :hours hours of :timezone.', ['hours' => $c['tolerance_hours'], 'timezone' => $c['required_timezone']]),
                'out_of_range' => __('Timezone is :minutes minutes from :timezone (allowed: :hours hours).', ['minutes' => $c['offset_difference_minutes'], 'timezone' => $c['required_timezone'], 'hours' => $c['tolerance_hours']]),
                'missing' => __('No timezone on the profile; :timezone is required.', ['timezone' => $c['required_timezone']]),
                'invalid' => __('The profile timezone is not a recognised timezone; :timezone is required.', ['timezone' => $c['required_timezone']]),
                default => __('The required timezone is not a recognised timezone.'),
            };
        }

        return implode(' ', $parts);
    }
}

<?php

/*
 * POD matching criteria and explainable scoring (UNA-013). The only scoring code is
 * App\Services\Matching\PodMatchScorer; see docs/pod-matching.md for the exact formulas.
 */

return [

    // Stored on every pod_matches row. Bump it whenever a formula, weight or rule changes so stored
    // results can be told apart from results of the new rules.
    'version' => 'una-013.1',

    // Weighted criteria (App\Enums\Matching\MatchCriterion). Must add up to 100. Only criteria the
    // worker requirement configures are scored; the active weights are renormalized to 100.
    'weights' => [
        'skill_coverage' => 25,
        'skill_proficiency' => 15,
        'experience' => 15,
        'role' => 15,
        'availability' => 10,
        'location' => 10,
        'project_type' => 10,
    ],

    // Enum-based availability (profiles.availability_status) resolved to a date relative to the
    // evaluation time.
    'availability' => [
        'within_two_weeks_days' => 14,
        'within_one_month_months' => 1,
    ],

    // UNA-014: minimum match score (0-100) a POD requires before a match qualifies. New PODs copy
    // their company's default (workspaces.default_minimum_match_threshold) or, without one, this.
    'default_minimum_threshold' => 70,

    // UNA-014: persisted matches are reprocessed in chunks of this size after a configuration change.
    'recalculation_chunk_size' => 100,

    // Upper bound for pod_worker_requirements.timezone_tolerance_hours.
    'max_timezone_tolerance_hours' => 14,

];

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

    // UNA-016: the active user-to-POD algorithm (a key of 'algorithms'). Each driver must implement
    // App\Contracts\PodMatchingAlgorithm; its version is reported by the class itself (the
    // deterministic one uses 'version' above). Only listed drivers can be selected.
    'algorithm' => 'deterministic',
    'algorithms' => [
        'deterministic' => [
            'class' => App\Services\Matching\DeterministicPodMatchingService::class,
        ],
    ],

    // UNA-016: queued match generation.
    'queue' => 'matching',
    'jobs' => [
        'coordinator_chunk_size' => 200, // PODs or employees discovered per chunk
        'tries' => 5,
        'backoff' => [30, 120, 300], // seconds between attempts (the last value repeats)
        // Seconds per attempt. Must stay below the queue connection's retry_after (database: 90),
        // otherwise a running job can be handed to a second worker; raise both together.
        'timeout' => 80,
        'unique_for' => 3600,        // seconds a queued duplicate is collapsed for
    ],

    // UNA-017: employee recommendation screen.
    'recommendations' => [
        'per_page' => 9,
        'strong_score' => 80, // display-only: "strong matches" summary metric
    ],

    // Upper bound for pod_worker_requirements.timezone_tolerance_hours.
    'max_timezone_tolerance_hours' => 14,

];

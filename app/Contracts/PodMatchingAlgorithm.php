<?php

namespace App\Contracts;

use App\Models\Pod;
use App\Services\Matching\PodMatchingResult;
use DateTimeInterface;

/**
 * A user-to-POD matching algorithm (UNA-016). The queued pair job depends on this contract only.
 *
 * Implementations score and persist requirement-level results (pod_matches / pod_match_reasons,
 * through PodMatchService) and return a PodMatchingResult. They never change memberships: the
 * caller hands the persisted best match to PodMatchingWorkflowService, which re-checks
 * qualification from the stored status, reasons, threshold, requirement and POD.
 *
 * The production implementation is the deterministic structured-data matcher
 * (DeterministicPodMatchingService). A future AI-assisted ranker may only enrich or re-rank on top
 * of the deterministic baseline: mandatory disqualification, profile eligibility, publication,
 * threshold and membership rules stay authoritative outside the algorithm (docs/pod-matching.md §16).
 */
interface PodMatchingAlgorithm
{
    /** Stable key stored in pod_matches.algorithm, e.g. "deterministic". */
    public function key(): string;

    /** Stable version stored in pod_matches.scoring_version. */
    public function version(): string;

    /** One generation: every active requirement of $pod for $candidate at one timestamp. */
    public function match(Pod $pod, HasAccountType $candidate, ?DateTimeInterface $generatedAt = null): PodMatchingResult;
}

<?php

namespace App\Services\Matching;

use App\Models\PodMatch;
use App\Models\PodMatchReason;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates an employee against one active POD worker requirement and persists the explainable
 * snapshot (UNA-013).
 *
 * - Only regular employee accounts with a saved profile are candidates (IneligibleCandidate).
 * - One pod_matches row per (user, requirement): re-evaluation upserts it and replaces all of its
 *   reasons in one DB::transaction(..., 3).
 * - Never touches pod_users, workspace memberships or notifications: a match is information only.
 */
class PodMatchService
{
    private const ATTEMPTS = 3;

    public function __construct(private PodMatchScorer $scorer)
    {
    }

    /** Score without persisting (loads the relations the scorer needs once). */
    public function score(PodWorkerRequirement $requirement, User $candidate, ?DateTimeInterface $evaluatedAt = null): MatchResult
    {
        $this->assertEvaluable($requirement, $candidate);

        return $this->scorer->score($requirement, $candidate, $evaluatedAt ?? now());
    }

    /** Score and persist; returns the saved match with its ordered reasons loaded. */
    public function evaluate(PodWorkerRequirement $requirement, User $candidate, ?DateTimeInterface $evaluatedAt = null): PodMatch
    {
        $result = $this->score($requirement, $candidate, $evaluatedAt);

        return $this->persist($requirement, $candidate, $result);
    }

    public function persist(PodWorkerRequirement $requirement, User $candidate, MatchResult $result): PodMatch
    {
        return DB::transaction(function () use ($requirement, $candidate, $result) {
            $now = now();
            $key = ['user_id' => $candidate->id, 'pod_worker_requirement_id' => $requirement->id];

            // Atomic insert-or-update on the unique (user_id, pod_worker_requirement_id) pair.
            PodMatch::query()->upsert([$key + [
                'pod_id' => $requirement->pod_id,
                'score' => $result->score(),
                'status' => $result->status->value,
                'active_weight' => $result->activeWeight,
                'scoring_version' => $result->scoringVersion,
                'evaluated_at' => $result->evaluatedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]], array_keys($key), ['pod_id', 'score', 'status', 'active_weight', 'scoring_version', 'evaluated_at', 'updated_at']);

            $match = PodMatch::query()->where($key)->lockForUpdate()->firstOrFail();

            PodMatchReason::query()->where('pod_match_id', $match->id)->delete();
            PodMatchReason::query()->insert(array_map(fn (CriterionResult $c) => [
                'pod_match_id' => $match->id,
                'criterion' => $c->criterion->value,
                'position' => $c->criterion->position(),
                'active' => $c->active,
                'possible_points' => $c->possiblePoints,
                'awarded_points' => $c->awardedPoints(),
                'passed' => $c->passed,
                'mandatory' => $c->mandatory,
                'disqualifying' => $c->disqualifying,
                'code' => $c->code->value,
                'context' => $c->context === [] ? null : json_encode($c->context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ], $result->criteria));

            return $match->load('reasons');
        }, self::ATTEMPTS);
    }

    /** @throws IneligibleCandidate|InactiveRequirement */
    private function assertEvaluable(PodWorkerRequirement $requirement, User $candidate): void
    {
        if (!$requirement->is_active) {
            throw new InactiveRequirement();
        }
        if (!$candidate->isRegularUser() || !$candidate->isEmployee()) {
            throw IneligibleCandidate::notEmployee();
        }

        $requirement->loadMissing(PodMatchScorer::REQUIREMENT_RELATIONS);
        $candidate->loadMissing(PodMatchScorer::CANDIDATE_RELATIONS);
        if (!$candidate->profile->exists) {
            throw IneligibleCandidate::withoutProfile();
        }
    }
}

<?php

namespace App\Services\Matching;

use App\Contracts\HasAccountType;
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
 * - persistMany() stores several requirement results of one candidate atomically (UNA-015).
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
        return $this->persistMany($candidate, [[$requirement, $result]])[$requirement->id];
    }

    /**
     * Persist several requirement results of one candidate in one DB::transaction(..., 3)
     * (UNA-015), so a POD-level run never leaves a partial set. Uses a constant number of queries:
     * one upsert on the unique (user_id, pod_worker_requirement_id) pair, one locked read, one
     * delete of the old reasons and one insert of the new ones. Matches of other users or other
     * requirements are never touched.
     *
     * @param array<int, array{0: PodWorkerRequirement, 1: MatchResult}> $entries
     * @return array<int, PodMatch> keyed by pod_worker_requirement_id, ordered by requirement id, reasons loaded
     */
    public function persistMany(User $candidate, array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        return DB::transaction(function () use ($candidate, $entries) {
            $now = now();
            $rows = [];
            foreach ($entries as [$requirement, $result]) {
                $rows[] = [
                    'user_id' => $candidate->id,
                    'pod_worker_requirement_id' => $requirement->id,
                    'pod_id' => $requirement->pod_id,
                    'score' => $result->score(),
                    'status' => $result->status->value,
                    'active_weight' => $result->activeWeight,
                    'scoring_version' => $result->scoringVersion,
                    'evaluated_at' => $result->evaluatedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Atomic insert-or-update on the unique (user_id, pod_worker_requirement_id) pair.
            PodMatch::query()->upsert($rows, ['user_id', 'pod_worker_requirement_id'],
                ['pod_id', 'score', 'status', 'active_weight', 'scoring_version', 'evaluated_at', 'updated_at']);

            $requirementIds = array_column($rows, 'pod_worker_requirement_id');
            $matches = PodMatch::query()->where('user_id', $candidate->id)->whereIn('pod_worker_requirement_id', $requirementIds)
                ->orderBy('pod_worker_requirement_id')->lockForUpdate()->get()->keyBy('pod_worker_requirement_id');

            PodMatchReason::query()->whereIn('pod_match_id', $matches->pluck('id'))->delete();
            $reasons = [];
            foreach ($entries as [$requirement, $result]) {
                foreach ($result->criteria as $c) {
                    $reasons[] = [
                        'pod_match_id' => $matches->get($requirement->id)->id,
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
                    ];
                }
            }
            PodMatchReason::query()->insert($reasons);

            $matches->load('reasons');

            return $matches->all();
        }, self::ATTEMPTS);
    }

    /**
     * Only regular employee accounts with a saved profile are candidates (UNA-002 identity API).
     * Loads the candidate relations the scorer needs once. Clients, companies, administrators and
     * unclassified accounts are refused.
     *
     * @throws IneligibleCandidate
     */
    public function assertCandidate(HasAccountType $candidate): User
    {
        if (!$candidate instanceof User || !$candidate->isRegularUser() || !$candidate->isEmployee()) {
            throw IneligibleCandidate::notEmployee();
        }
        $candidate->loadMissing(PodMatchScorer::CANDIDATE_RELATIONS);
        if (!$candidate->profile->exists) {
            throw IneligibleCandidate::withoutProfile();
        }

        return $candidate;
    }

    /** @throws IneligibleCandidate|InactiveRequirement */
    private function assertEvaluable(PodWorkerRequirement $requirement, User $candidate): void
    {
        if (!$requirement->is_active) {
            throw new InactiveRequirement();
        }
        $this->assertCandidate($candidate);
        $requirement->loadMissing(PodMatchScorer::REQUIREMENT_RELATIONS);
    }
}

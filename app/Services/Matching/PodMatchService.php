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
 * - persistMany()/persistGeneration() store several requirement results of one candidate
 *   atomically (UNA-015), with the algorithm key and a stale-generation guard (UNA-016).
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
     * Persist several requirement results of one candidate (UNA-015); see persistGeneration().
     *
     * @param array<int, array{0: PodWorkerRequirement, 1: MatchResult}> $entries
     * @return array<int, PodMatch> keyed by pod_worker_requirement_id, ordered by requirement id, reasons loaded
     */
    public function persistMany(User $candidate, array $entries, string $algorithm = DeterministicPodMatchingService::KEY): array
    {
        return $this->persistGeneration($candidate, $entries, $algorithm)->matches;
    }

    /**
     * Persist one generation of requirement results of one candidate in one DB::transaction(..., 3)
     * with a constant number of queries (UNA-015/016):
     *
     *  1. lock the candidate's existing rows for these requirements,
     *  2. skip every entry whose stored evaluated_at (generation timestamp) is NEWER than the entry's
     *     (stale protection: an older, delayed job never overwrites a newer row or its reasons;
     *     equal timestamps are rewritten idempotently),
     *  3. upsert the remaining rows on the unique (user_id, pod_worker_requirement_id) pair with the
     *     algorithm key, version and timestamp,
     *  4. replace their reasons (delete + insert, inside the same transaction, so a partial reason
     *     set is never visible).
     *
     * The returned matches are the stored rows (the newer ones for stale entries). Matches of other
     * users or other requirements are never touched.
     *
     * @param array<int, array{0: PodWorkerRequirement, 1: MatchResult}> $entries
     */
    public function persistGeneration(User $candidate, array $entries, string $algorithm): PersistedGeneration
    {
        if ($entries === []) {
            return new PersistedGeneration([], []);
        }
        if ($algorithm === '') {
            throw new \InvalidArgumentException('A match must record the algorithm that produced it.');
        }

        return DB::transaction(function () use ($candidate, $entries, $algorithm) {
            $now = now();
            // Stored in the application timezone: the datetime column keeps no offset, so a timestamp
            // in another zone would be read back as a different instant (and break the stale guard).
            $appTimezone = (string) config('app.timezone', 'UTC');
            $requirementIds = array_map(fn (array $entry) => (int) $entry[0]->id, $entries);
            $stored = PodMatch::query()->where('user_id', $candidate->id)->whereIn('pod_worker_requirement_id', $requirementIds)
                ->lockForUpdate()->get(['id', 'pod_worker_requirement_id', 'evaluated_at'])->keyBy('pod_worker_requirement_id');

            $fresh = [];
            $stale = [];
            foreach ($entries as [$requirement, $result]) {
                $existing = $stored->get($requirement->id);
                if ($existing !== null && $existing->evaluated_at->getTimestamp() > $result->evaluatedAt->getTimestamp()) {
                    $stale[] = (int) $requirement->id;
                    continue;
                }
                $fresh[] = [$requirement, $result];
            }

            if ($fresh !== []) {
                PodMatch::query()->upsert(array_map(fn (array $entry) => [
                    'user_id' => $candidate->id,
                    'pod_worker_requirement_id' => $entry[0]->id,
                    'pod_id' => $entry[0]->pod_id,
                    'score' => $entry[1]->score(),
                    'status' => $entry[1]->status->value,
                    'algorithm' => $algorithm,
                    'active_weight' => $entry[1]->activeWeight,
                    'scoring_version' => $entry[1]->scoringVersion,
                    'evaluated_at' => $entry[1]->evaluatedAt->setTimezone($appTimezone),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $fresh), ['user_id', 'pod_worker_requirement_id'],
                    ['pod_id', 'score', 'status', 'algorithm', 'active_weight', 'scoring_version', 'evaluated_at', 'updated_at']);
            }

            $matches = PodMatch::query()->where('user_id', $candidate->id)->whereIn('pod_worker_requirement_id', $requirementIds)
                ->orderBy('pod_worker_requirement_id')->lockForUpdate()->get()->keyBy('pod_worker_requirement_id');

            if ($fresh !== []) {
                $freshMatchIds = array_map(fn (array $entry) => $matches->get($entry[0]->id)->id, $fresh);
                PodMatchReason::query()->whereIn('pod_match_id', $freshMatchIds)->delete();
                $reasons = [];
                foreach ($fresh as [$requirement, $result]) {
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
            }

            $matches->load('reasons');

            return new PersistedGeneration($matches->all(), $stale);
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

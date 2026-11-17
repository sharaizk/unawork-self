<?php

namespace App\Services\Matching;

use App\Contracts\HasAccountType;
use App\Contracts\PodMatchingAlgorithm;
use App\Enums\Matching\MatchStatus;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deterministic user-to-POD matching (UNA-015), a facade over the UNA-013 pieces:
 *
 *   PodMatchScorer       pure criterion scoring (one employee vs one requirement)
 *   PodMatchService      candidate rules + persistence (pod_matches / pod_match_reasons)
 *   this service         one employee vs every active requirement of one POD, one timestamp,
 *                        one transaction, deterministic best requirement
 *   PodMatchingWorkflowService  UNA-014 membership behaviour, separate and never called here
 *
 * It never creates or changes a membership, workspace access, permissions, state history or
 * notifications, and therefore never grants Outer or Inner Circle access.
 *
 * UNA-016: the default App\Contracts\PodMatchingAlgorithm (key "deterministic", version =
 * config('pod_matching.version')), used by the queued GenerateEmployeePodMatch job.
 */
class DeterministicPodMatchingService implements PodMatchingAlgorithm
{
    public const KEY = 'deterministic';

    /** Status precedence for the best match: eligible first, then disqualified, then not scorable. */
    public const STATUS_PRECEDENCE = [
        MatchStatus::ELIGIBLE->value => 0,
        MatchStatus::DISQUALIFIED->value => 1,
        MatchStatus::NOT_SCORABLE->value => 2,
    ];

    public function __construct(
        private PodMatchScorer $scorer,
        private PodMatchService $matches,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function version(): string
    {
        return $this->scorer->version();
    }

    /**
     * Evaluate $candidate against every active worker requirement of $pod at one evaluation time
     * (now() once, when none is given) and persist all results atomically.
     *
     * @throws IneligibleCandidate for anything but a regular employee with a saved profile
     */
    public function match(Pod $pod, HasAccountType $candidate, ?DateTimeInterface $generatedAt = null): PodMatchingResult
    {
        return $this->matchWith($pod, $this->activeRequirements($pod), $this->matches->assertCandidate($candidate), $this->timestamp($generatedAt));
    }

    /**
     * Match an explicit list of candidates to one POD with one requirement load and one timestamp.
     * Never discovers candidates. An ineligible candidate throws IneligibleCandidate, like match().
     *
     * @param iterable<HasAccountType> $candidates
     * @return \Generator<int, PodMatchingResult> keyed by user id
     */
    public function matchMany(Pod $pod, iterable $candidates, ?DateTimeInterface $evaluatedAt = null): \Generator
    {
        $at = $this->timestamp($evaluatedAt);
        $requirements = $this->activeRequirements($pod);
        if ($candidates instanceof Collection) {
            $candidates->loadMissing(array_merge(['roles'], PodMatchScorer::CANDIDATE_RELATIONS));
        }

        foreach ($candidates as $candidate) {
            $user = $this->matches->assertCandidate($candidate);
            yield $user->id => $this->matchWith($pod, $requirements, $user, $at);
        }
    }

    /**
     * Sort key of a persisted match: [status precedence, -score in hundredths, requirement id].
     * The smallest key is the best match.
     *
     * @return array{int, int, int}
     */
    public static function rank(PodMatch $match): array
    {
        return [
            self::STATUS_PRECEDENCE[$match->status->value],
            -MatchQualification::hundredths($match->score),
            (int) $match->pod_worker_requirement_id,
        ];
    }

    // ------------------------------------------------------------------ internals

    /** @param Collection<int, PodWorkerRequirement> $requirements */
    private function matchWith(Pod $pod, Collection $requirements, User $candidate, CarbonImmutable $at): PodMatchingResult
    {
        $version = $this->scorer->version();
        if ($requirements->isEmpty()) {
            return new PodMatchingResult((int) $pod->id, (int) $candidate->id, $at, $version, [], null, null, PodMatchingResult::NO_ACTIVE_REQUIREMENTS, self::KEY);
        }

        $entries = $requirements->map(fn (PodWorkerRequirement $r) => [$r, $this->scorer->score($r, $candidate, $at)])->values()->all();
        // A delayed, older generation never overwrites newer rows (UNA-016); the stored rows are returned.
        $generation = $this->matches->persistGeneration($candidate, $entries, self::KEY);
        $persisted = $generation->matches;

        $byRequirement = $requirements->keyBy('id');
        foreach ($persisted as $requirementId => $match) {
            $match->setRelation('requirement', $byRequirement->get($requirementId));
        }
        ksort($persisted);

        $best = collect($persisted)->sort(fn (PodMatch $a, PodMatch $b) => self::rank($a) <=> self::rank($b))->first();

        return new PodMatchingResult(
            (int) $pod->id,
            (int) $candidate->id,
            $at,
            $version,
            $persisted,
            $best,
            MatchQualification::of($best, $best->requirement, $pod),
            $generation->isStale() ? PodMatchingResult::STALE_GENERATION : null,
            self::KEY,
            $generation->isStale(),
        );
    }

    /** Active requirements of the POD with the scorer's relations, loaded once, ordered by id. */
    private function activeRequirements(Pod $pod): Collection
    {
        return PodWorkerRequirement::query()
            ->where('pod_id', $pod->id)
            ->where('is_active', true)
            ->with(PodMatchScorer::REQUIREMENT_RELATIONS)
            ->orderBy('id')
            ->get()
            ->each(fn (PodWorkerRequirement $r) => $r->setRelation('pod', $pod));
    }

    private function timestamp(?DateTimeInterface $evaluatedAt): CarbonImmutable
    {
        return CarbonImmutable::instance($evaluatedAt ?? now());
    }
}

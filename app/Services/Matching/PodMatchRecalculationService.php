<?php

namespace App\Services\Matching;

use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodWorkerRequirement;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Re-evaluates persisted matches after a POD matching configuration change (UNA-014).
 *
 * Scope: only candidates already represented by pod_matches; no candidate discovery. Each match is
 * re-scored and re-applied through PodMatchingWorkflowService::evaluateAndApply(), in id-ordered
 * chunks, with per-candidate failures reported and counted instead of stopping the run.
 *
 * Writers (PodService) call configurationSnapshot() before and after their change inside their
 * transaction, then scheduleChanges(): matches of requirements that became inactive are deleted in
 * that same transaction, and the re-evaluation is registered with DB::afterCommit(), so it runs
 * only once the configuration is committed (and never after a rollback), outside the
 * configuration transaction.
 */
class PodMatchRecalculationService
{
    /** Requirement attributes that change a score or eligibility (headcount/description do not). */
    public const REQUIREMENT_MATCHING_ATTRIBUTES = [
        'job_title_id', 'minimum_years_experience', 'minimum_skill_proficiency', 'preferred_work_mode',
        'preferred_engagement_type', 'location', 'is_active',
        'required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type', 'mandatory_criteria',
    ];

    /** Relations each chunk eager loads, so a run does not issue per-match relation queries. */
    public const CHUNK_RELATIONS = [
        'requirement.pod', 'requirement.skills', 'requirement.jobTitle',
        'user.roles', 'user.skills', 'user.profile.experiences', 'user.profile.preferredRoles', 'user.profile.projectTypes',
    ];

    private ?RecalculationSummary $lastSummary = null;

    public function __construct(private PodMatchingWorkflowService $workflow)
    {
    }

    // ------------------------------------------------------------------ change detection

    /**
     * Matching-relevant configuration of a POD: mode, threshold, status and a fingerprint per
     * requirement (its matching attributes plus sorted skill ids).
     *
     * @return array{pod: array, requirements: array<int, array{active: bool, fingerprint: string}>}
     */
    public function configurationSnapshot(int $podId): array
    {
        $pod = Pod::query()->whereKey($podId)->first(['id', 'matching_mode', 'minimum_match_threshold', 'status']);
        $requirements = PodWorkerRequirement::query()->where('pod_id', $podId)->with('skills:skills.id')->orderBy('id')->get();

        return [
            'pod' => [
                'matching_mode' => $pod?->matching_mode?->value,
                'minimum_match_threshold' => $pod?->minimum_match_threshold,
                'status' => $pod?->status?->value,
            ],
            'requirements' => $requirements->mapWithKeys(fn (PodWorkerRequirement $r) => [$r->id => [
                'active' => (bool) $r->is_active,
                'fingerprint' => $this->fingerprint($r),
            ]])->all(),
        ];
    }

    /**
     * Compare two snapshots taken inside the writer's transaction and schedule the consequences.
     * Returns what was scheduled (for logging/tests): ['pod' => bool, 'requirements' => int[], 'invalidated' => int].
     */
    public function scheduleChanges(int $podId, array $before, array $after): array
    {
        $podChanged = $before['pod'] !== $after['pod'];
        $changed = [];
        $inactive = [];
        foreach ($after['requirements'] as $id => $now) {
            $was = $before['requirements'][$id] ?? null;
            if ($was === null) {
                continue; // new requirement: no persisted candidates yet
            }
            if (!$now['active']) {
                if ($was['active'] || $was['fingerprint'] !== $now['fingerprint']) {
                    $inactive[] = $id;
                }
                continue;
            }
            if ($was['fingerprint'] !== $now['fingerprint']) {
                $changed[] = $id;
            }
        }

        // An inactive requirement never keeps a (possibly qualified-looking) snapshot. Same transaction.
        $invalidated = $inactive ? PodMatch::query()->whereIn('pod_worker_requirement_id', $inactive)->delete() : 0;

        if ($podChanged) {
            $this->afterCommit(fn () => $this->recalculatePod($podId));
        } elseif ($changed !== []) {
            $this->afterCommit(fn () => $this->recalculateRequirements($changed));
        }

        return ['pod' => $podChanged, 'requirements' => $podChanged ? [] : $changed, 'invalidated' => $invalidated];
    }

    /** Re-process every persisted match of a POD after the surrounding transaction commits (e.g. publication). */
    public function schedulePod(int $podId): void
    {
        $this->afterCommit(fn () => $this->recalculatePod($podId));
    }

    // ------------------------------------------------------------------ recalculation

    public function recalculatePod(int $podId, ?DateTimeInterface $at = null): RecalculationSummary
    {
        return $this->run(PodMatch::query()->where('pod_id', $podId), $at);
    }

    /** @param int[] $requirementIds */
    public function recalculateRequirements(array $requirementIds, ?DateTimeInterface $at = null): RecalculationSummary
    {
        return $this->run(PodMatch::query()->whereIn('pod_worker_requirement_id', array_values(array_unique(array_map('intval', $requirementIds)))), $at);
    }

    /** Summary of the most recent run in this process (tests, diagnostics). */
    public function lastSummary(): ?RecalculationSummary
    {
        return $this->lastSummary;
    }

    private function run($query, ?DateTimeInterface $at): RecalculationSummary
    {
        $summary = new RecalculationSummary();
        $at ??= now();
        $invalid = (clone $query)->whereHas('requirement', fn ($q) => $q->where('is_active', false))->pluck('id');
        if ($invalid->isNotEmpty()) {
            $summary->invalidated += PodMatch::query()->whereIn('id', $invalid)->delete();
        }

        $query->with(self::CHUNK_RELATIONS)->chunkById((int) config('pod_matching.recalculation_chunk_size', 100), function ($matches) use ($summary, $at) {
            foreach ($matches as $match) {
                try {
                    $summary->record($this->workflow->evaluateAndApply($match->requirement, $match->user, $at));
                } catch (IneligibleCandidate|InactiveRequirement) {
                    $summary->skipped++;
                } catch (Throwable $e) {
                    report($e);
                    $summary->failed++;
                }
            }
        });

        return $this->lastSummary = $summary;
    }

    /** Runs $callback after the current transaction commits (immediately when none is open); failures are reported, never thrown at the writer. */
    private function afterCommit(\Closure $callback): void
    {
        DB::afterCommit(function () use ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private function fingerprint(PodWorkerRequirement $requirement): string
    {
        $values = [];
        foreach (self::REQUIREMENT_MATCHING_ATTRIBUTES as $attribute) {
            $value = $requirement->getAttribute($attribute);
            $values[$attribute] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                is_bool($value) => $value,
                $attribute === 'mandatory_criteria' => array_map(fn ($c) => $c->value, $requirement->mandatoryCriteria()),
                $value === null => null,
                default => (string) $value,
            };
        }
        $values['skills'] = $requirement->skills->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return json_encode($values, JSON_THROW_ON_ERROR);
    }
}

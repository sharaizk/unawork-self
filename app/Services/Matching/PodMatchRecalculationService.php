<?php

namespace App\Services\Matching;

use App\Enums\Pod\PodStatus;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodWorkerRequirement;

/**
 * Detects POD matching configuration changes and schedules queued regeneration (UNA-014, made
 * asynchronous in UNA-016). Nothing is scored in the web process any more.
 *
 * PodService calls configurationSnapshot() before and after a save inside its transaction, then
 * scheduleChanges():
 *  - matches of requirements that became inactive are deleted in that same transaction, so no
 *    stale recommendation survives;
 *  - for a published POD whose mode, threshold, status or any requirement's matching fingerprint
 *    changed (or a requirement was added/removed), a GenerateMatchesForPod job is dispatched
 *    after commit through MatchGenerationDispatcher (never after a rollback);
 *  - drafts are never generated; publication (schedulePod()) picks their configuration up.
 */
class PodMatchRecalculationService
{
    /** Requirement attributes that change a score or eligibility (headcount/description do not). */
    public const REQUIREMENT_MATCHING_ATTRIBUTES = [
        'job_title_id', 'minimum_years_experience', 'minimum_skill_proficiency', 'preferred_work_mode',
        'preferred_engagement_type', 'location', 'is_active',
        'required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type', 'mandatory_criteria',
    ];

    public function __construct(private MatchGenerationDispatcher $dispatcher)
    {
    }

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
     * Compare two snapshots taken inside the writer's transaction, invalidate matches of
     * inactivated requirements now, and queue regeneration after commit when needed.
     *
     * @return array{dispatched: bool, invalidated: int}
     */
    public function scheduleChanges(int $podId, array $before, array $after): array
    {
        $inactive = [];
        foreach ($after['requirements'] as $id => $now) {
            $was = $before['requirements'][$id] ?? null;
            if (!$now['active'] && ($was === null || $was['active'] || $was['fingerprint'] !== $now['fingerprint'])) {
                $inactive[] = $id;
            }
        }
        // An inactive requirement never keeps a (possibly qualified-looking) snapshot. Same transaction.
        $invalidated = $inactive ? PodMatch::query()->whereIn('pod_worker_requirement_id', $inactive)->delete() : 0;

        $changed = $before['pod'] !== $after['pod']
            || array_keys($before['requirements']) !== array_keys($after['requirements'])
            || array_column($before['requirements'], 'fingerprint') !== array_column($after['requirements'], 'fingerprint');
        $published = ($after['pod']['status'] ?? null) === PodStatus::PUBLISHED->value;

        if ($changed && $published) {
            $this->dispatcher->podAfterCommit($podId);
        }

        return ['dispatched' => $changed && $published, 'invalidated' => $invalidated];
    }

    /** Queue generation for every eligible employee once the surrounding transaction commits (publication). */
    public function schedulePod(int $podId): void
    {
        $this->dispatcher->podAfterCommit($podId);
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

<?php

namespace Tests\Feature\Matching;

use App\Enums\Pod\PodStatus;
use App\Models\JobTitle;
use App\Models\Pod;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use App\Services\Matching\PodMatchingWorkflowService;
use App\Services\Matching\PodMatchOutcome;
use App\Services\Matching\PodMatchRecalculationService;
use App\Services\Matching\RecalculationSummary;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixtures for the UNA-014 matching-workflow tests. In-memory SQLite only.
 *
 * qualifiedEmployee() has a 100% profile (completeEmployeeProfile: role "Engineer", PHP/Laravel/SQL
 * advanced). Against perfectRequirement() it scores 100.00; against partialRequirement() (PHP +
 * Docker, role) it scores (12.50 + 15.00) / 40 = 68.75.
 */
abstract class WorkflowTestCase extends MatchingTestCase
{
    protected JobTitle $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engineer = JobTitle::firstOrCreate(['title' => 'Engineer']);
    }

    protected function configurePod(?string $mode, int $threshold = 70, bool $published = true): Pod
    {
        $this->pod ??= $this->pods()->createDraft($this->company, ['title' => 'Platform Team']);
        $this->pod->forceFill([
            'matching_mode' => $mode,
            'minimum_match_threshold' => $threshold,
            'status' => $published ? PodStatus::PUBLISHED : PodStatus::DRAFT,
            'published_at' => $published ? now() : null,
        ])->save();

        return $this->pod = $this->pod->fresh();
    }

    protected function perfectRequirement(array $attributes = []): PodWorkerRequirement
    {
        return $this->requirement($attributes + ['job_title_id' => $this->engineer->id], [$this->php->id]);
    }

    protected function partialRequirement(): PodWorkerRequirement
    {
        return $this->requirement(['job_title_id' => $this->engineer->id], [$this->php->id, $this->docker->id]);
    }

    protected function qualifiedEmployee(): User
    {
        $user = $this->account('employee');
        $this->completeEmployeeProfile($user);

        return $user->fresh();
    }

    /** An employee below the profile minimum (no about/role/links/education...). */
    protected function incompleteEmployee(): User
    {
        return $this->candidate(['job_title_id' => $this->engineer->id], [$this->php->id => 'expert']);
    }

    protected function workflow(): PodMatchingWorkflowService
    {
        return app(PodMatchingWorkflowService::class);
    }

    protected function apply(PodWorkerRequirement $requirement, User $candidate): PodMatchOutcome
    {
        return $this->workflow()->evaluateAndApply($requirement->fresh(), $candidate->fresh(), $this->at());
    }

    protected function memberships(): array
    {
        return $this->snapshot(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions', 'notifications']);
    }

    /** Replace the container's recalculation service with one that records each run and the transaction level. */
    protected function spyRecalculation(): object
    {
        $spy = new class(app(PodMatchingWorkflowService::class)) extends PodMatchRecalculationService {
            public array $calls = [];

            public function recalculatePod(int $podId, ?DateTimeInterface $at = null): RecalculationSummary
            {
                $this->calls[] = ['pod', $podId, DB::transactionLevel()];

                return parent::recalculatePod($podId, $at);
            }

            public function recalculateRequirements(array $requirementIds, ?DateTimeInterface $at = null): RecalculationSummary
            {
                $this->calls[] = ['requirements', array_values($requirementIds), DB::transactionLevel()];

                return parent::recalculateRequirements($requirementIds, $at);
            }
        };
        $this->app->instance(PodMatchRecalculationService::class, $spy);

        return $spy;
    }

    /** setup-form row for an existing requirement, everything as stored. */
    protected function requirementRow(PodWorkerRequirement $requirement, array $overrides = []): array
    {
        $requirement = $requirement->fresh('skills');

        return $overrides + [
            'id' => $requirement->id,
            'job_title_id' => $requirement->job_title_id,
            'headcount' => $requirement->headcount,
            'skill_ids' => $requirement->skills->pluck('id')->all(),
            'is_active' => $requirement->is_active ? 1 : 0,
        ] + $requirement->matchingAttributes();
    }
}

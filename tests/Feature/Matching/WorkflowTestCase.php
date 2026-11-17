<?php

namespace Tests\Feature\Matching;

use App\Enums\Pod\PodStatus;
use App\Models\JobTitle;
use App\Models\Pod;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use App\Services\Matching\PodMatchingWorkflowService;
use App\Services\Matching\PodMatchOutcome;
use App\Jobs\Matching\GenerateEmployeePodMatch;
use App\Jobs\Matching\GenerateMatchesForEmployee;
use App\Jobs\Matching\GenerateMatchesForPod;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Bus;

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

    /** How many GenerateMatchesForPod jobs were queued for $podId (PodTestCase fakes the bus). */
    protected function podJobsFor(int $podId): int
    {
        return Bus::dispatched(GenerateMatchesForPod::class, fn (GenerateMatchesForPod $job) => $job->podId === $podId)->count();
    }

    /**
     * Run every queued matching job that has not run yet, including the pair jobs the coordinators
     * queue, until none is left (like a worker would). Each job's unique lock is released as the
     * queue worker does. @return string[] job class => handle() result, in run order
     */
    protected function drainMatchingJobs(): array
    {
        $ran = [];
        do {
            $pending = collect([GenerateMatchesForEmployee::class, GenerateMatchesForPod::class, GenerateEmployeePodMatch::class])
                ->flatMap(fn ($class) => Bus::dispatched($class)->all())
                ->reject(fn ($job) => isset($this->ranJobs[spl_object_id($job)]));
            foreach ($pending as $job) {
                $this->ranJobs[spl_object_id($job)] = true;
                $ran[] = [class_basename($job), $this->runJob($job)];
            }
        } while ($pending->isNotEmpty());

        return $ran;
    }

    /** Handle one queued job like a worker: release its unique lock, then resolve handle() dependencies. */
    protected function runJob(object $job): mixed
    {
        (new UniqueLock(app(CacheRepository::class)))->release($job);

        return $this->app->call([$job, 'handle']);
    }

    private array $ranJobs = [];

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

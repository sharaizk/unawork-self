<?php

namespace Tests\Feature\Matching;

use App\Contracts\HasAccountType;
use App\Contracts\PodMatchingAlgorithm;
use App\Enums\Matching\MatchStatus;
use App\Enums\Pod\PodStatus;
use App\Enums\PodUser\State;
use App\Enums\UserWorkspace\Permission;
use App\Jobs\Matching\GenerateEmployeePodMatch;
use App\Jobs\Matching\GenerateMatchesForEmployee;
use App\Jobs\Matching\GenerateMatchesForPod;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodMatchReason;
use App\Models\PodUser;
use App\Models\PodUserStateHistory;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Services\Matching\DeterministicPodMatchingService;
use App\Services\Matching\PodMatchingAlgorithmResolver;
use App\Services\Matching\PodMatchingResult;
use App\Services\Matching\PodMatchPresenter;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/** UNA-016: job contracts, coordinators, the pair job, stale protection and the algorithm contract. */
class MatchGenerationJobsTest extends WorkflowTestCase
{
    private const T1 = '2026-06-15T12:00:00+00:00';
    private const T2 = '2026-06-16T12:00:00+00:00';

    private function pairJob(User $user, ?Pod $pod = null, string $at = self::T1): GenerateEmployeePodMatch
    {
        return new GenerateEmployeePodMatch($user->id, ($pod ?? $this->pod)->id, $at);
    }

    private function pairUsers(): array
    {
        return Bus::dispatched(GenerateEmployeePodMatch::class)->map(fn ($j) => [$j->userId, $j->podId])->values()->all();
    }

    // ------------------------------------------------------------------ contracts

    public function test_jobs_are_queued_unique_scalar_and_bounded(): void
    {
        $jobs = [
            [new GenerateMatchesForEmployee(7, self::T1), 'matching:employee:7'],
            [new GenerateMatchesForPod(9, self::T1), 'matching:pod:9'],
            [new GenerateEmployeePodMatch(7, 9, self::T1), 'matching:employee-pod:7:9'],
        ];
        foreach ($jobs as [$job, $uniqueId]) {
            $this->assertInstanceOf(ShouldQueue::class, $job);
            $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
            $this->assertSame(['matching', $uniqueId, 5, [30, 120, 300], 80, 3600], [$job->queue, $job->uniqueId(), $job->tries, $job->backoff, $job->timeout, $job->uniqueFor]);
            $this->assertSame(self::T1, $job->generatedAt);
            $payload = serialize($job);
            $this->assertStringNotContainsString('Illuminate\\Database\\Eloquent', $payload, 'no models in the payload');
            foreach ((new \ReflectionClass($job))->getConstructor()->getParameters() as $parameter) {
                $this->assertContains((string) $parameter->getType(), ['int', 'string'], $parameter->getName());
            }
        }
        $this->assertLessThan(90, config('pod_matching.jobs.timeout'), 'below the database queue retry_after');

        // A duplicate is collapsed while the first is queued.
        GenerateEmployeePodMatch::dispatch(7, 9, self::T1);
        GenerateEmployeePodMatch::dispatch(7, 9, self::T2);
        $this->assertSame(1, Bus::dispatched(GenerateEmployeePodMatch::class)->count());
    }

    // ------------------------------------------------------------------ coordinators

    public function test_the_employee_coordinator_fans_out_to_published_pods_with_active_requirements(): void
    {
        config(['pod_matching.jobs.coordinator_chunk_size' => 1]);
        $published = $this->configurePod('recommend_only');
        $this->perfectRequirement();
        $draft = $this->pods()->createDraft($this->company, ['title' => 'Draft']);
        $this->requirementOn($draft);
        $empty = $this->pods()->createDraft($this->company, ['title' => 'Empty']);
        $empty->forceFill(['status' => PodStatus::PUBLISHED])->save();
        $inactive = $this->pods()->createDraft($this->company, ['title' => 'Inactive']);
        $inactive->forceFill(['status' => PodStatus::PUBLISHED])->save();
        $this->requirementOn($inactive)->forceFill(['is_active' => false])->save();
        $second = $this->pods()->createDraft($this->company, ['title' => 'Second']);
        $second->forceFill(['status' => PodStatus::PUBLISHED])->save();
        $this->requirementOn($second);
        $user = $this->qualifiedEmployee();

        $result = $this->runJob(new GenerateMatchesForEmployee($user->id, self::T1));

        $this->assertSame('dispatched:2', $result);
        $this->assertSame([[$user->id, $published->id], [$user->id, $second->id]], $this->pairUsers(), 'published + active only, POD id order');
        $this->assertSame([self::T1], Bus::dispatched(GenerateEmployeePodMatch::class)->map->generatedAt->unique()->values()->all());
        $this->assertSame(0, PodMatch::count(), 'coordinators score nothing');
    }

    public function test_the_pod_coordinator_fans_out_to_eligible_employees_in_id_order(): void
    {
        config(['pod_matching.jobs.coordinator_chunk_size' => 1]);
        $pod = $this->configurePod('recommend_only');
        $this->perfectRequirement();
        $a = $this->qualifiedEmployee();
        $incomplete = $this->incompleteEmployee();
        $noProfile = $this->account('employee');
        $company = $this->account('company');
        $this->completeEmployeeProfile($company); // a company with profile data is still not a candidate
        $b = $this->qualifiedEmployee();

        $this->assertSame('dispatched:2', $this->runJob(new GenerateMatchesForPod($pod->id, self::T1)));
        $this->assertSame([[$a->id, $pod->id], [$b->id, $pod->id]], $this->pairUsers());
        $this->assertNotContains($incomplete->id, array_column($this->pairUsers(), 0));
        $this->assertNotContains($noProfile->id, array_column($this->pairUsers(), 0));
    }

    public function test_deleted_or_ineligible_targets_are_expected_no_ops(): void
    {
        $pod = $this->configurePod('automatic', 0);
        $this->perfectRequirement();
        $user = $this->qualifiedEmployee();

        $this->assertSame('skipped_not_employee', $this->runJob(new GenerateMatchesForEmployee(999, self::T1)));
        $this->assertSame('skipped_not_employee', $this->runJob(new GenerateMatchesForEmployee($this->company->id, self::T1)));
        $this->assertSame('skipped_pod_unavailable', $this->runJob(new GenerateMatchesForPod(999, self::T1)));
        $this->assertSame('skipped_missing', $this->runJob(new GenerateEmployeePodMatch(999, $pod->id, self::T1)));
        $this->assertSame('skipped_not_employee', $this->runJob(new GenerateEmployeePodMatch($this->company->id, $pod->id, self::T1)));

        $pod->forceFill(['status' => PodStatus::DRAFT])->save();
        $this->assertSame('skipped_pod_unavailable', $this->runJob(new GenerateMatchesForPod($pod->id, self::T1)));
        $this->assertSame('skipped_pod_not_published', $this->runJob($this->pairJob($user)));
        $this->assertSame([0, 0], [PodMatch::count(), PodUser::where('user_id', $user->id)->count()]);
    }

    // ------------------------------------------------------------------ the pair job

    public function test_the_pair_job_generates_the_whole_pod_once_and_applies_only_the_best(): void
    {
        $this->configurePod('pending_approval', 60);
        $partial = $this->partialRequirement();
        $perfect = $this->perfectRequirement();
        $user = $this->qualifiedEmployee();

        $this->assertSame('applied:created', $this->runJob($this->pairJob($user)));

        $matches = PodMatch::orderBy('pod_worker_requirement_id')->get();
        $this->assertSame([[$partial->id, '68.75'], [$perfect->id, '100.00']], $matches->map(fn ($m) => [$m->pod_worker_requirement_id, $m->score])->all());
        $this->assertSame([['deterministic', config('pod_matching.version'), '2026-06-15 12:00:00']],
            $matches->map(fn ($m) => [$m->algorithm, $m->scoring_version, $m->evaluated_at->utc()->format('Y-m-d H:i:s')])->unique()->values()->all(), 'one algorithm, version and timestamp');
        $this->assertSame(18, PodMatchReason::count());
        $this->assertSame([State::PENDING_BUSINESS_APPROVAL, 1], [PodUser::where('user_id', $user->id)->sole()->state, PodUserStateHistory::whereIn('pod_user_id', PodUser::where('user_id', $user->id)->pluck('id'))->count()], 'one membership for the best match');

        $presented = app(PodMatchPresenter::class)->present($matches->last());
        $this->assertSame(['deterministic', config('pod_matching.version')], [$presented['algorithm'], $presented['algorithm_version']]);
        $this->assertTrue(CarbonImmutable::parse($presented['generated_at'])->equalTo(CarbonImmutable::parse(self::T1)), 'same instant as the job timestamp');
        $this->assertSame($presented['generated_at'], $presented['evaluated_at']);
    }

    public function test_each_mode_is_idempotent_across_repeated_jobs_and_never_reaches_the_inner_circle(): void
    {
        foreach (['recommend_only' => null, 'pending_approval' => State::PENDING_BUSINESS_APPROVAL, 'automatic' => State::OUTER_CIRCLE] as $mode => $state) {
            $this->pod = null;
            $this->configurePod($mode, 0);
            $this->perfectRequirement();
            $user = $this->qualifiedEmployee();

            // Reason rows of an equal-timestamp regeneration are replaced (new ids), so compare content.
            $state_ = fn () => array_map(
                fn (array $rows) => array_map(fn (array $row) => array_diff_key($row, array_flip(['id', 'created_at', 'updated_at'])), $rows),
                $this->snapshot(['pod_matches', 'pod_match_reasons', 'pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions', 'notifications']),
            );
            $this->runJob($this->pairJob($user));
            $after = $state_();
            $this->runJob($this->pairJob($user));
            $this->runJob($this->pairJob($user));

            $this->assertEquals($after, $state_(), "{$mode}: no duplicates");
            $this->assertSame($state, PodUser::where('pod_id', $this->pod->id)->where('user_id', $user->id)->first()?->state, $mode);
            $permissions = UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $user->id)->first()?->getPermissionNames()->all() ?? [];
            $this->assertNotContains(Permission::PROJECTS_INDEX->value, $permissions, "{$mode}: no Inner Circle access");
        }
        $this->assertSame(0, PodUser::where('state', State::INNER_CIRCLE->value)->where('permission', '!=', 'Owner')->count());
    }

    public function test_a_failure_after_persistence_retries_safely(): void
    {
        $this->configurePod('automatic', 0);
        $this->perfectRequirement();
        $user = $this->qualifiedEmployee();
        DB::statement('CREATE TRIGGER fail_history BEFORE INSERT ON pod_user_state_histories WHEN NEW.reason = \'Qualified automatically by POD matching\' BEGIN SELECT RAISE(ABORT, \'injected\'); END');

        try {
            $this->runJob($this->pairJob($user));
            $this->fail('the failure must escape so the queue retries');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame([1, 9, 0], [PodMatch::count(), PodMatchReason::count(), PodUser::where('user_id', $user->id)->count()], 'match committed, membership rolled back as a whole');

        DB::statement('DROP TRIGGER fail_history');
        $this->assertSame('applied:created', $this->runJob($this->pairJob($user)), 'the retry (same timestamp) finishes');
        $this->assertSame([1, 9, 1], [PodMatch::count(), PodMatchReason::count(), PodUser::where('user_id', $user->id)->count()]);
    }

    public function test_one_failing_pair_does_not_corrupt_another(): void
    {
        $this->configurePod('automatic', 0);
        $this->perfectRequirement();
        [$broken, $fine] = [$this->qualifiedEmployee(), $this->qualifiedEmployee()];
        DB::statement("CREATE TRIGGER fail_one BEFORE INSERT ON pod_match_reasons WHEN NEW.pod_match_id IN (SELECT id FROM pod_matches WHERE user_id = {$broken->id}) BEGIN SELECT RAISE(ABORT, 'injected'); END");

        try {
            $this->runJob($this->pairJob($broken));
            $this->fail('escaped');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame('applied:created', $this->runJob($this->pairJob($fine)));

        $this->assertSame(0, PodMatch::where('user_id', $broken->id)->count(), 'no partial match: rows and reasons roll back together');
        $this->assertSame([1, 9], [PodMatch::where('user_id', $fine->id)->count(), PodMatchReason::count()]);
        $this->assertSame(0, PodUser::where('user_id', $broken->id)->count());
    }

    // ------------------------------------------------------------------ stale protection

    public function test_an_older_job_never_overwrites_a_newer_generation(): void
    {
        $this->configurePod('recommend_only', 0);
        $requirement = $this->perfectRequirement();
        $user = $this->qualifiedEmployee();

        $this->runJob($this->pairJob($user, null, self::T2));
        $newer = $this->snapshot(['pod_matches', 'pod_match_reasons']);

        // The requirement changes, then the delayed older job finally runs: it must not win.
        $requirement->skills()->sync([$this->php->id, $this->docker->id]);
        $this->configurePod('automatic', 0);
        $this->assertSame('stale', $this->runJob($this->pairJob($user, null, self::T1)));
        $this->assertEquals($newer, $this->snapshot(['pod_matches', 'pod_match_reasons']), 'rows and reasons untouched');
        $this->assertSame(0, PodUser::where('user_id', $user->id)->count(), 'no membership from a stale generation');

        // The same timestamp again is an idempotent regeneration with current data.
        $this->assertSame('applied:created', $this->runJob($this->pairJob($user, null, self::T2)));
        $this->assertSame(['68.75', '2026-06-16 12:00:00'], [PodMatch::sole()->score, PodMatch::sole()->evaluated_at->utc()->format('Y-m-d H:i:s')]);

        // The UNA-014 single-requirement entry point respects the same guard.
        $outcome = $this->workflow()->evaluateAndApply($requirement->fresh(), $user, CarbonImmutable::parse(self::T1));
        $this->assertSame('stale_generation', $outcome->reason);
        $this->assertSame('2026-06-16 12:00:00', PodMatch::sole()->evaluated_at->utc()->format('Y-m-d H:i:s'));
    }

    // ------------------------------------------------------------------ algorithm contract

    public function test_the_deterministic_algorithm_is_the_configured_default(): void
    {
        $algorithm = app(PodMatchingAlgorithm::class);
        $this->assertInstanceOf(DeterministicPodMatchingService::class, $algorithm);
        $this->assertSame(['deterministic', config('pod_matching.version')], [$algorithm->key(), $algorithm->version()]);
        $this->assertSame(['key', 'version', 'match'], array_map(fn ($m) => $m->getName(), (new \ReflectionClass(PodMatchingAlgorithm::class))->getMethods()), 'no membership API on the contract');

        foreach (['missing' => [], 'bad' => ['bad' => ['class' => \stdClass::class]], 'liar' => ['liar' => ['class' => DeterministicPodMatchingService::class]]] as $key => $algorithms) {
            config(['pod_matching.algorithm' => $key, 'pod_matching.algorithms' => $algorithms + config('pod_matching.algorithms')]);
            try {
                app(PodMatchingAlgorithmResolver::class)->resolve();
                $this->fail("{$key} resolved");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString("[{$key}]", $e->getMessage());
            }
        }
    }

    public function test_a_bound_algorithm_is_used_without_changing_the_job_and_cannot_bypass_mandatory_rules(): void
    {
        $this->configurePod('automatic', 0);
        $clean = $this->perfectRequirement();
        $mandatory = $this->perfectRequirement(['project_type' => 'hourly', 'mandatory_criteria' => ['project_type']]);
        $user = $this->qualifiedEmployee();

        // Test-only algorithm: deterministic baseline, but it "promotes" the disqualified requirement as eligible.
        $fake = new class(app(DeterministicPodMatchingService::class), $mandatory->id) implements PodMatchingAlgorithm {
            public int $calls = 0;

            public function __construct(private DeterministicPodMatchingService $baseline, private int $promote)
            {
            }

            public function key(): string
            {
                return 'fake-ranker';
            }

            public function version(): string
            {
                return 'test.1';
            }

            public function match(Pod $pod, HasAccountType $candidate, ?DateTimeInterface $generatedAt = null): PodMatchingResult
            {
                $this->calls++;
                $base = $this->baseline->match($pod, $candidate, $generatedAt);
                $best = $base->matches[$this->promote];
                $best->forceFill(['status' => MatchStatus::ELIGIBLE->value, 'score' => 99])->save();

                return new PodMatchingResult($base->podId, $base->userId, $base->evaluatedAt, $this->version(), $base->matches, $best->fresh(), null, null, $this->key());
            }
        };
        $this->app->instance(PodMatchingAlgorithm::class, $fake);

        $this->assertSame('applied:none', $this->runJob($this->pairJob($user)), 'stored disqualifying reason wins');
        $this->assertSame(1, $fake->calls, 'the job used the bound contract');
        $this->assertSame(0, PodUser::where('user_id', $user->id)->count());

        // A legitimately eligible result chosen by the bound algorithm still goes through the workflow only.
        $mandatory->forceFill(['is_active' => false])->save();
        $fake = new class(app(DeterministicPodMatchingService::class)) implements PodMatchingAlgorithm {
            public function __construct(private DeterministicPodMatchingService $baseline)
            {
            }

            public function key(): string
            {
                return 'fake-ranker';
            }

            public function version(): string
            {
                return 'test.1';
            }

            public function match(Pod $pod, HasAccountType $candidate, ?DateTimeInterface $generatedAt = null): PodMatchingResult
            {
                return $this->baseline->match($pod, $candidate, $generatedAt);
            }
        };
        $this->app->instance(PodMatchingAlgorithm::class, $fake);
        $this->assertSame('applied:created', $this->runJob($this->pairJob($user, null, self::T2)));
        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $user->id)->sole()->state, 'Outer Circle at most');
        $this->assertSame('fake-ranker', $fake->key());
        $this->assertSame('100.00', PodMatch::where('user_id', $user->id)->where('pod_worker_requirement_id', $clean->id)->value('score'));
    }

    // ------------------------------------------------------------------ real queue

    public function test_a_failing_job_is_retried_and_recorded_in_failed_jobs_by_the_queue(): void
    {
        (require database_path('migrations/2024_07_25_145424_create_jobs_table.php'))->up();
        (require database_path('migrations/2024_07_25_153249_create_failed_jobs_table.php'))->up();
        config(['queue.default' => 'database', 'pod_matching.jobs.tries' => 2, 'pod_matching.jobs.backoff' => [0]]);
        Bus::swap(app(\Illuminate\Contracts\Bus\QueueingDispatcher::class)); // undo PodTestCase's Bus::fake()

        $this->configurePod('automatic', 0);
        $this->perfectRequirement();
        $user = $this->qualifiedEmployee();
        DB::statement("CREATE TRIGGER fail_all BEFORE INSERT ON pod_match_reasons BEGIN SELECT RAISE(ABORT, 'injected'); END");

        GenerateEmployeePodMatch::dispatch($user->id, $this->pod->id, self::T1);
        $this->assertSame(['matching'], DB::table('jobs')->pluck('queue')->all());

        foreach (range(1, 2) as $attempt) {
            \Illuminate\Support\Facades\Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'matching', '--once' => true, '--sleep' => 0]);
        }

        $this->assertSame([0, 1], [DB::table('jobs')->count(), DB::table('failed_jobs')->count()], 'retried, then recorded as failed');
        $this->assertStringContainsString('GenerateEmployeePodMatch', DB::table('failed_jobs')->value('payload'));
        $this->assertSame([0, 0, 0], [PodMatch::count(), PodMatchReason::count(), PodUser::where('user_id', $user->id)->count()], 'nothing partial or falsely successful');
    }

    private function requirementOn(Pod $pod): \App\Models\PodWorkerRequirement
    {
        $requirement = new \App\Models\PodWorkerRequirement(['is_active' => true, 'headcount' => 1, 'job_title_id' => $this->engineer->id]);
        $requirement->forceFill(['pod_id' => $pod->id])->save();
        $requirement->skills()->sync([$this->php->id]);

        return $requirement;
    }
}

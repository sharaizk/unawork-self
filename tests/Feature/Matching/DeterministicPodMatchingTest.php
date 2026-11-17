<?php

namespace Tests\Feature\Matching;

use App\Enums\Matching\MatchCriterion as C;
use App\Enums\Matching\MatchStatus;
use App\Enums\Matching\MembershipAction;
use App\Enums\PodUser\State;
use App\Models\Client;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodMatchReason;
use App\Models\PodUser;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use App\Services\Matching\DeterministicPodMatchingService;
use App\Services\Matching\IneligibleCandidate;
use App\Services\Matching\PodMatchingResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** UNA-015: one employee against every active requirement of a POD; scoring only, no membership. */
class DeterministicPodMatchingTest extends WorkflowTestCase
{
    private function service(): DeterministicPodMatchingService
    {
        return app(DeterministicPodMatchingService::class);
    }

    private function match(User $candidate, ?Pod $pod = null, ?CarbonImmutable $at = null): PodMatchingResult
    {
        return $this->service()->match(($pod ?? $this->pod)->fresh(), $candidate->fresh(), $at ?? $this->at());
    }

    private function requirementOn(Pod $pod, array $attributes, array $skillIds): PodWorkerRequirement
    {
        $requirement = new PodWorkerRequirement(['is_active' => true, 'headcount' => 1] + $attributes);
        $requirement->forceFill(['pod_id' => $pod->id])->save();
        $requirement->skills()->sync($skillIds);

        return $requirement;
    }

    /** Comparable view of a match: everything except ids and timestamps of the row itself. */
    private function matchView(PodMatch $match): array
    {
        return [
            'requirement' => (int) $match->pod_worker_requirement_id,
            'score' => (string) $match->score,
            'status' => $match->status->value,
            'active_weight' => $match->active_weight,
            'version' => $match->scoring_version,
            'evaluated_at' => $match->evaluated_at->utc()->format('Y-m-d H:i:s'),
            'reasons' => $match->reasons->map(fn (PodMatchReason $r) => [
                $r->criterion->value, $r->position, $r->active, $r->possible_points, (string) $r->awarded_points,
                $r->passed, $r->mandatory, $r->disqualifying, $r->code->value, $r->context,
            ])->all(),
        ];
    }

    public function test_every_active_requirement_is_evaluated_and_persisted(): void
    {
        $this->configurePod('recommend_only', 70);
        $perfect = $this->perfectRequirement();
        $partial = $this->partialRequirement();
        $inactive = $this->perfectRequirement();
        $inactive->forceFill(['is_active' => false])->save();
        $candidate = $this->qualifiedEmployee();

        $result = $this->match($candidate);

        $this->assertSame([$perfect->id, $partial->id], array_keys($result->matches));
        $this->assertSame([$this->pod->id, $candidate->id, self::AT, config('pod_matching.version'), null],
            [$result->podId, $result->userId, $result->evaluatedAt->format('Y-m-d H:i:s'), $result->scoringVersion, $result->reason]);
        $this->assertSame(0, PodMatch::where('pod_worker_requirement_id', $inactive->id)->count(), 'inactive requirement neither evaluated nor stored');
        $this->assertSame(2, PodMatch::count());
        $this->assertSame(18, PodMatchReason::count());

        foreach ($result->matches as $requirementId => $match) {
            $this->assertTrue($match->relationLoaded('reasons'));
            $this->assertSame(C::values(), $match->reasons->map(fn ($r) => $r->criterion->value)->all(), 'ordered reasons');
            $this->assertEquals($this->matchView(PodMatch::with('reasons')->find($match->id)), $this->matchView($match), 'returned = persisted');
        }
        $this->assertSame(['100.00', '68.75'], [$result->matches[$perfect->id]->score, $result->matches[$partial->id]->score]);

        $this->assertSame([$perfect->id, '100.00', MatchStatus::ELIGIBLE, [], true], [
            $result->bestRequirementId(), $result->bestScore(), $result->bestStatus(), $result->bestFailedMandatory(), $result->bestMeetsThreshold(),
        ]);
        $this->assertTrue($result->bestIsQualified());
    }

    public function test_an_eligible_requirement_outranks_a_higher_scoring_disqualified_one(): void
    {
        $this->configurePod('recommend_only', 70);
        // (25 + 15 + 0) / 50 = 80.00, but the mandatory project type fails.
        $disqualified = $this->perfectRequirement(['project_type' => 'hourly', 'mandatory_criteria' => ['project_type']]);
        $eligible = $this->partialRequirement(); // 68.75
        $notScorable = $this->requirement(['preferred_work_mode' => 'remote']);

        $result = $this->match($this->qualifiedEmployee());

        $this->assertSame(['80.00', 'disqualified'], [$result->matches[$disqualified->id]->score, $result->matches[$disqualified->id]->status->value]);
        $this->assertSame([$eligible->id, '68.75', MatchStatus::ELIGIBLE, false], [$result->bestRequirementId(), $result->bestScore(), $result->bestStatus(), $result->bestMeetsThreshold()]);
        $this->assertSame([0, -6875, $eligible->id], DeterministicPodMatchingService::rank($result->best));

        // Without the eligible one, a disqualified match still beats a not-scorable one.
        $eligible->forceFill(['is_active' => false])->save();
        $result = $this->match($this->qualifiedEmployee());
        $this->assertSame([$disqualified->id, MatchStatus::DISQUALIFIED, [C::PROJECT_TYPE], false], [$result->bestRequirementId(), $result->bestStatus(), $result->bestFailedMandatory(), $result->bestIsQualified()]);
        $this->assertSame(MatchStatus::NOT_SCORABLE, $result->matches[$notScorable->id]->status);
    }

    public function test_highest_eligible_score_wins_and_the_lowest_requirement_id_breaks_ties(): void
    {
        $this->configurePod('recommend_only', 0);
        $partial = $this->partialRequirement();
        $first = $this->perfectRequirement();
        $second = $this->perfectRequirement();

        $result = $this->match($this->qualifiedEmployee());

        $this->assertSame(['100.00', '100.00'], [$result->matches[$first->id]->score, $result->matches[$second->id]->score]);
        $this->assertSame($first->id, $result->bestRequirementId());
        $this->assertNotSame($partial->id, $result->bestRequirementId());
    }

    public function test_all_mandatory_failures_are_stored_and_returned_with_the_score(): void
    {
        $this->configurePod('recommend_only', 70);
        $requirement = $this->perfectRequirement([
            'project_type' => 'hourly', 'location' => 'Atlantis', 'preferred_work_mode' => 'onsite',
            'mandatory_criteria' => ['work_mode', 'project_type', 'location', 'role'],
        ]);

        $result = $this->match($this->qualifiedEmployee());
        $match = $result->matches[$requirement->id];

        $this->assertSame([C::LOCATION, C::PROJECT_TYPE, C::WORK_MODE], $result->bestFailedMandatory(), 'criterion order');
        $this->assertSame(MatchStatus::DISQUALIFIED, $match->status);
        $this->assertSame('66.67', $match->score, '(25 + 15 + 0 + 0) / 60, kept for diagnostics');
        $this->assertSame(['location', 'project_type', 'work_mode'], PodMatchReason::where('pod_match_id', $match->id)->where('mandatory', true)->where('disqualifying', true)->orderBy('position')->pluck('criterion')->map->value->all());
        $this->assertTrue(PodMatchReason::where('pod_match_id', $match->id)->where('criterion', 'role')->sole()->mandatory);
        $this->assertFalse(PodMatchReason::where('pod_match_id', $match->id)->where('criterion', 'role')->sole()->disqualifying, 'passed mandatory');

        $presented = app(\App\Services\Matching\PodMatchPresenter::class)->present($match);
        $this->assertSame(['location', 'project_type', 'work_mode'], array_column($presented['failed_mandatory'], 'criterion'));
    }

    public function test_a_pod_without_active_requirements_returns_an_empty_result_and_stores_nothing(): void
    {
        $this->configurePod('automatic', 0);
        $this->perfectRequirement()->forceFill(['is_active' => false])->save();

        $result = $this->match($this->qualifiedEmployee());

        $this->assertSame([[], null, null, null, MatchStatus::NOT_SCORABLE, [], false, false, PodMatchingResult::NO_ACTIVE_REQUIREMENTS],
            [$result->matches, $result->best, $result->bestRequirementId(), $result->bestScore(), $result->bestStatus(), $result->bestFailedMandatory(), $result->bestMeetsThreshold(), $result->hasMatches(), $result->reason]);
        $this->assertSame([0, 0], [PodMatch::count(), PodMatchReason::count()]);
    }

    public function test_re_running_is_repeatable_versioned_and_updates_rows_in_place(): void
    {
        $this->configurePod('recommend_only', 70);
        $this->perfectRequirement();
        $this->partialRequirement();
        $this->requirement(['minimum_hours_per_week' => 50, 'mandatory_criteria' => ['availability']]);
        $candidate = $this->qualifiedEmployee();

        $first = $this->match($candidate);
        $ids = PodMatch::orderBy('id')->pluck('id')->all();
        $second = $this->match($candidate);

        $this->assertSame(array_map(fn ($m) => $this->matchView($m), $first->matches), array_map(fn ($m) => $this->matchView($m), $second->matches));
        $this->assertSame($first->bestRequirementId(), $second->bestRequirementId());
        $this->assertSame($ids, PodMatch::orderBy('id')->pluck('id')->all(), 'same rows, no duplicates');
        $this->assertSame([3, 27], [PodMatch::count(), PodMatchReason::count()], 'stale reasons replaced');
        $this->assertSame([config('pod_matching.version')], PodMatch::distinct()->pluck('scoring_version')->all());
        $this->assertSame([self::AT], PodMatch::get()->map(fn ($m) => $m->evaluated_at->utc()->format('Y-m-d H:i:s'))->unique()->values()->all(), 'one timestamp for the whole run');
    }

    public function test_time_sensitive_criteria_use_the_supplied_timestamp(): void
    {
        $this->configurePod('recommend_only', 0);
        $requirement = $this->requirement(['job_title_id' => $this->role->id, 'minimum_years_experience' => 2, 'required_start_date' => '2026-07-01']);
        $candidate = $this->candidate(['job_title_id' => $this->role->id, 'availability_status' => 'within_two_weeks'], [], [
            ['job_title_id' => $this->role->id, 'start_date' => '2025-06-15', 'is_current' => true],
        ]);

        $early = $this->match($candidate, null, CarbonImmutable::parse('2026-06-15 12:00:00', 'UTC'))->matches[$requirement->id];
        $context = fn (PodMatch $m, string $criterion) => $m->reasons->firstWhere('criterion', C::from($criterion))->context;
        $this->assertSame([365, '2026-06-29', true], [$context($early, 'experience')['relevant_days'], $context($early, 'availability')['available_on'], $context($early, 'availability')['start_met']]);

        $late = $this->match($candidate, null, CarbonImmutable::parse('2026-06-20 12:00:00', 'UTC'))->matches[$requirement->id];
        $this->assertSame([370, '2026-07-04', false], [$context($late, 'experience')['relevant_days'], $context($late, 'availability')['available_on'], $context($late, 'availability')['start_met']]);
        $this->assertSame('2026-06-20 12:00:00', $late->evaluated_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_changing_structured_data_changes_only_the_affected_components(): void
    {
        $this->configurePod('recommend_only', 0);
        $requirement = $this->perfectRequirement(['project_type' => 'hourly', 'minimum_hours_per_week' => 30]);
        $candidate = $this->qualifiedEmployee();
        $reasons = fn () => collect($this->matchView($this->match($candidate)->matches[$requirement->id])['reasons'])->keyBy(0)->all();

        $before = $reasons();
        $candidate->profile->projectTypes()->forceCreate(['profile_id' => $candidate->profile->id, 'project_type' => 'hourly']);
        $afterCandidate = $reasons();
        $this->assertSame(['project_type'], array_keys(array_filter($afterCandidate, fn ($r, $k) => $r !== $before[$k], ARRAY_FILTER_USE_BOTH)));
        $this->assertSame([true, '10.00'], [$afterCandidate['project_type'][5], $afterCandidate['project_type'][4]]);

        $requirement->forceFill(['minimum_hours_per_week' => 45])->save();
        $afterRequirement = $reasons();
        $this->assertSame(['availability'], array_keys(array_filter($afterRequirement, fn ($r, $k) => $r !== $afterCandidate[$k], ARRAY_FILTER_USE_BOTH)));
        $this->assertSame([false, '0.00'], [$afterRequirement['availability'][5], $afterRequirement['availability'][4]]);
    }

    public function test_query_count_does_not_grow_with_requirements_or_criteria(): void
    {
        $this->configurePod('recommend_only', 70);
        $small = $this->pod;
        $this->perfectRequirement();
        $large = $this->pods()->createDraft($this->company, ['title' => 'Large']);
        foreach (range(1, 4) as $i) {
            $this->requirementOn($large, ['job_title_id' => $this->engineer->id, 'location' => 'Colombo', 'minimum_skill_proficiency' => 'advanced'], [$this->php->id, $this->sql->id]);
        }
        [$a, $b] = [$this->qualifiedEmployee(), $this->qualifiedEmployee()];

        $count = function (Pod $pod, User $candidate) {
            $pod = $pod->fresh();
            $candidate = $candidate->fresh();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->service()->match($pod, $candidate, $this->at());
            $log = collect(DB::getQueryLog())->pluck('query');
            DB::disableQueryLog();

            return $log;
        };

        $one = $count($small, $a);
        $four = $count($large, $b);
        $this->assertSame($one->count(), $four->count(), 'constant number of queries: ' . $four->implode(' | '));
        foreach (['"profile_experiences"', '"profile_preferred_roles"', '"profile_project_types"', '"user_skills"', '"pod_worker_requirement_skill"'] as $table) {
            $this->assertSame(1, $four->filter(fn ($q) => str_contains($q, $table))->count(), $table);
        }
    }

    public function test_only_employees_with_a_profile_can_be_matched(): void
    {
        $this->configurePod('recommend_only');
        $this->perfectRequirement();
        $client = Client::forceCreate(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'x']);
        $unclassified = User::factory()->create(['type' => 'user']);

        foreach (['company' => $this->account('company'), 'administrator' => $this->account('administrator'), 'client' => $client, 'unclassified' => $unclassified] as $type => $account) {
            try {
                $this->service()->match($this->pod, $account, $this->at());
                $this->fail("{$type} was matched");
            } catch (IneligibleCandidate) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $this->service()->match($this->pod, $this->account('employee'), $this->at());
            $this->fail('employee without a profile was matched');
        } catch (IneligibleCandidate $e) {
            $this->assertSame(IneligibleCandidate::withoutProfile()->getMessage(), $e->getMessage());
        }
        $this->assertSame(0, PodMatch::count());
    }

    public function test_matching_one_pod_never_touches_another_pods_matches(): void
    {
        $this->configurePod('recommend_only');
        $this->perfectRequirement();
        $other = $this->pods()->createDraft($this->company, ['title' => 'Other']);
        $this->requirementOn($other, ['job_title_id' => $this->engineer->id], [$this->php->id]);
        $candidate = $this->qualifiedEmployee();

        $this->match($candidate, $other, CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'));
        $otherRows = $this->snapshot(['pod_matches', 'pod_match_reasons']);
        $this->match($candidate);

        $this->assertSame(1, PodMatch::where('pod_id', $other->id)->count());
        $this->assertSame('2026-01-01 00:00:00', PodMatch::where('pod_id', $other->id)->sole()->evaluated_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1, PodMatch::forPodAndUser($this->pod->id, $candidate->id)->count());
        $this->assertSame(2, $candidate->podMatches()->count());
        $this->assertCount(1, array_filter($otherRows['pod_matches'], fn ($r) => (int) $r['pod_id'] === $other->id));
        $this->assertEquals(
            collect($otherRows['pod_matches'])->where('pod_id', $other->id)->values()->all(),
            collect($this->snapshot(['pod_matches'])['pod_matches'])->where('pod_id', $other->id)->values()->all(),
        );
    }

    public function test_the_deterministic_service_alone_never_changes_membership_or_access(): void
    {
        // Published POD in automatic mode with a qualified, complete-profile candidate: the worst case.
        $this->configurePod('automatic', 0);
        $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();
        $applicant = $this->qualifiedEmployee();
        app(\App\Services\Membership\PodStateTransitionService::class)->enter($this->pod, $applicant, State::PENDING_BUSINESS_APPROVAL, $applicant);
        $before = $this->snapshot(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions', 'model_has_roles', 'notifications']);

        $result = $this->match($candidate);
        $this->match($applicant);
        iterator_to_array($this->service()->matchMany($this->pod->fresh(), \App\Models\User::whereIn('id', [$candidate->id, $applicant->id])->get(), $this->at()));

        $this->assertTrue($result->bestIsQualified());
        $this->assertEquals($before, $this->snapshot(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions', 'model_has_roles', 'notifications']));
        $this->assertSame(0, PodUser::where('user_id', $candidate->id)->count(), 'no Outer or Inner Circle membership');
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $applicant->id)->sole()->state, 'applicant not advanced');
    }

    public function test_match_many_uses_one_timestamp_and_only_the_given_candidates(): void
    {
        $this->configurePod('recommend_only');
        $this->perfectRequirement();
        [$a, $b] = [$this->qualifiedEmployee(), $this->qualifiedEmployee()];
        $this->qualifiedEmployee(); // not passed in: never discovered

        $results = iterator_to_array($this->service()->matchMany($this->pod->fresh(), User::whereIn('id', [$a->id, $b->id])->get()));

        $this->assertSame([$a->id, $b->id], array_keys($results));
        $this->assertSame(2, PodMatch::count());
        $this->assertSame(1, PodMatch::distinct()->count('evaluated_at'), 'now() taken once at the boundary');
    }

    public function test_the_workflow_consumes_the_persisted_best_match_without_rescoring(): void
    {
        $this->configurePod('automatic', 70);
        $this->partialRequirement();
        $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();
        $result = $this->match($candidate);
        $stored = $this->snapshot(['pod_matches', 'pod_match_reasons']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $outcome = app(\App\Services\Matching\PodMatchingWorkflowService::class)->applyPersisted(PodMatch::find($result->best->id));
        $writes = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => preg_match('/^(insert|update|delete).*"pod_match/i', $q));
        DB::disableQueryLog();

        $this->assertSame([MembershipAction::CREATED, State::OUTER_CIRCLE], [$outcome->action, $outcome->membershipState]);
        $this->assertSame([], $writes->all(), 'no re-scoring or re-persisting');
        $this->assertEquals($stored, $this->snapshot(['pod_matches', 'pod_match_reasons']));
        $this->assertNotContains(\App\Enums\UserWorkspace\Permission::PROJECTS_INDEX->value,
            \App\Models\UserWorkspace::where('workspace_id', $this->pod->workspace->id)->where('user_id', $candidate->id)->sole()->getPermissionNames()->all(), 'never Inner Circle access');
    }
}

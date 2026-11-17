<?php

namespace Tests\Feature\Matching;

use App\Enums\Matching\MatchCriterion as C;
use App\Enums\Matching\MatchStatus;
use App\Http\Resources\PodMatchResource;
use App\Models\PodMatch;
use App\Models\PodMatchReason;
use App\Services\Matching\InactiveRequirement;
use App\Services\Matching\IneligibleCandidate;
use App\Services\Matching\PodMatchPresenter;
use App\Services\Matching\PodMatchService;
use Illuminate\Support\Facades\DB;

/** UNA-013: PodMatchService persistence, re-evaluation, FK cleanup, presenter and candidate rules. */
class PodMatchServiceTest extends MatchingTestCase
{
    private function service(): PodMatchService
    {
        return app(PodMatchService::class);
    }

    public function test_reasons_persist_in_order_with_stable_codes_and_structured_context(): void
    {
        $requirement = $this->fullRequirement(['mandatory_criteria' => ['skill_coverage']]);
        $candidate = $this->perfectCandidate();
        $match = $this->service()->evaluate($requirement, $candidate, $this->at());

        $this->assertSame([$this->pod->id, $requirement->id, $candidate->id, '100.00', MatchStatus::ELIGIBLE, 100, 'una-013.1', self::AT],
            [$match->pod_id, $match->pod_worker_requirement_id, $match->user_id, $match->score, $match->status, $match->active_weight, $match->scoring_version, $match->evaluated_at->format('Y-m-d H:i:s')]);
        $this->assertSame(C::values(), $match->reasons->map(fn ($r) => $r->criterion->value)->all());
        $this->assertSame(range(0, 8), $match->reasons->pluck('position')->all());

        $row = DB::table('pod_match_reasons')->where('pod_match_id', $match->id)->where('criterion', 'skill_coverage')->first();
        $this->assertSame(['skills_all_matched', '25.00', 25, 1, 1, 0], [$row->code, number_format((float) $row->awarded_points, 2), (int) $row->possible_points, (int) $row->passed, (int) $row->mandatory, (int) $row->disqualifying]);
        $this->assertSame(['id' => $this->php->id, 'name' => 'PHP'], json_decode($row->context, true)['matched'][0]);
    }

    public function test_re_evaluation_updates_the_unique_match_and_replaces_stale_reasons(): void
    {
        $requirement = $this->requirement(['job_title_id' => $this->role->id, 'mandatory_criteria' => ['role']], [$this->php->id]);
        $candidate = $this->candidate(['job_title_id' => $this->role->id], [$this->php->id => 'expert']);

        $first = $this->service()->evaluate($requirement, $candidate, $this->at());
        $oldReasonIds = $first->reasons->pluck('id')->all();
        $this->assertSame(MatchStatus::ELIGIBLE, $first->status);

        $candidate->profile->forceFill(['job_title_id' => $this->designer->id])->save();
        $second = $this->service()->evaluate($requirement, $candidate->fresh(), $this->at()->addDay());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PodMatch::count());
        $this->assertSame([MatchStatus::DISQUALIFIED, '62.50', '2026-06-16'], [$second->status, $second->score, $second->evaluated_at->format('Y-m-d')]);
        $this->assertSame(count(C::cases()), PodMatchReason::count(), 'reasons replaced, not appended');
        $this->assertSame([], array_intersect($oldReasonIds, PodMatchReason::pluck('id')->all()));
        $this->assertTrue(PodMatchReason::where('criterion', 'role')->sole()->disqualifying);
    }

    public function test_the_presenter_returns_translated_ordered_displayable_entries(): void
    {
        $requirement = $this->fullRequirement(['mandatory_criteria' => ['skill_coverage', 'location']]);
        $candidate = $this->perfectCandidate(['timezone' => null]);
        $candidate->skills()->detach($this->sql->id);
        $match = $this->service()->evaluate($requirement, $candidate->fresh(), $this->at());

        $presented = app(PodMatchPresenter::class)->present($match->fresh());

        $this->assertSame(['disqualified', false], [$presented['status'], $presented['eligible']]);
        $this->assertSame([['criterion' => 'skill_coverage', 'label' => 'Required skills'], ['criterion' => 'location', 'label' => 'Location and timezone']], $presented['failed_mandatory']);
        $this->assertStringContainsString('Required skills, Location and timezone', $presented['summary']);
        $this->assertSame(C::values(), array_column($presented['criteria'], 'criterion'));

        $skills = $presented['criteria'][0];
        $this->assertSame(['Required skills', false, true, true, '12.50', 25], [$skills['label'], $skills['passed'], $skills['mandatory'], $skills['disqualifying'], $skills['awarded_points'], $skills['possible_points']]);
        $this->assertSame('Has 1 of 2 required skills. Missing: SQL.', $skills['explanation']);
        $this->assertSame([['id' => $this->sql->id, 'name' => 'SQL']], $skills['details']['missing']);

        $location = $presented['criteria'][5];
        $this->assertSame('Located in Colombo, Sri Lanka. No timezone on the profile; Asia/Colombo is required.', $location['explanation']);

        foreach ($presented['criteria'] as $entry) {
            $this->assertNotSame('', $entry['explanation'], $entry['criterion']);
            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $entry['explanation'], 'no unreplaced placeholders');
        }

        $json = (new PodMatchResource($match->load('reasons')))->resolve();
        $this->assertSame([$match->id, $this->pod->id, $requirement->id, $candidate->id, 'disqualified'], [$json['id'], $json['pod_id'], $json['pod_worker_requirement_id'], $json['user_id'], $json['status']]);
        $this->assertCount(count(C::cases()), $json['criteria']);
    }

    public function test_presenter_explains_a_not_scorable_match(): void
    {
        $requirement = $this->requirement(['preferred_work_mode' => 'remote']);
        $match = $this->service()->evaluate($requirement, $this->candidate(), $this->at());

        $presented = app(PodMatchPresenter::class)->present($match);
        $this->assertSame(['not_scorable', '0.00', 'Not scorable: this worker requirement configures no scored criteria.'], [$presented['status'], $presented['score'], $presented['summary']]);
        $this->assertSame('Not required by this worker requirement; not scored.', $presented['criteria'][0]['explanation']);
        $this->assertSame('Work mode is not compatible (Not set for Remote).', $presented['criteria'][7]['explanation']);
    }

    public function test_deleting_a_parent_removes_its_match_snapshots_and_reasons(): void
    {
        $requirement = $this->fullRequirement();
        $other = $this->fullRequirement();
        [$a, $b, $c] = [$this->perfectCandidate(), $this->perfectCandidate(), $this->perfectCandidate()];
        foreach ([$a, $b, $c] as $candidate) {
            $this->service()->evaluate($requirement, $candidate, $this->at());
        }
        $this->service()->evaluate($other, $a, $this->at());
        $this->assertSame([4, 36], [PodMatch::count(), PodMatchReason::count()]);

        DB::table('pod_matches')->where('user_id', $c->id)->delete();
        $this->assertSame([3, 27], [PodMatch::count(), PodMatchReason::count()], 'reasons never outlive their match');

        DB::table('users')->where('id', $b->id)->delete();
        $this->assertSame([2, 18], [PodMatch::count(), PodMatchReason::count()], 'user deleted');

        $other->skills()->detach();
        $other->delete();
        $this->assertSame([1, 9], [PodMatch::count(), PodMatchReason::count()], 'requirement deleted');

        DB::table('pods')->where('id', $this->pod->id)->delete();
        $this->assertSame([0, 0], [PodMatch::count(), PodMatchReason::count()], 'POD deleted');
    }

    public function test_matching_never_changes_memberships(): void
    {
        $requirement = $this->fullRequirement(['mandatory_criteria' => ['role']]);
        $eligible = $this->perfectCandidate();
        $disqualified = $this->perfectCandidate(['job_title_id' => $this->designer->id]);
        $before = $this->snapshot(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'user_projects', 'notifications', 'model_has_permissions']);

        $this->service()->evaluate($requirement, $eligible, $this->at());
        $this->service()->evaluate($requirement, $disqualified, $this->at());

        $this->assertEquals($before, $this->snapshot(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'user_projects', 'notifications', 'model_has_permissions']));
        $this->assertSame(0, DB::table('pod_users')->whereIn('user_id', [$eligible->id, $disqualified->id])->count());
    }

    public function test_only_employee_accounts_with_a_profile_are_candidates(): void
    {
        $requirement = $this->fullRequirement();

        foreach (['company', 'administrator'] as $type) {
            try {
                $this->service()->evaluate($requirement, $this->account($type), $this->at());
                $this->fail("{$type} was evaluated");
            } catch (IneligibleCandidate) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $this->service()->evaluate($requirement, $this->account('employee'), $this->at());
            $this->fail('an employee without a profile was evaluated');
        } catch (IneligibleCandidate) {
            $this->addToAssertionCount(1);
        }

        $requirement->forceFill(['is_active' => false])->save();
        try {
            $this->service()->evaluate($requirement, $this->perfectCandidate(), $this->at());
            $this->fail('an inactive requirement was evaluated');
        } catch (InactiveRequirement) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, PodMatch::count());
    }
}

<?php

namespace Tests\Feature\Matching;

use App\Enums\Company\MatchingMode;
use App\Enums\Matching\MatchStatus;
use App\Enums\Pod\PodMatchingMode;
use App\Enums\Pod\PodStatus;
use App\Enums\PodUser\State;
use App\Models\Pod;
use App\Models\PodMatch;
use App\Models\PodUser;
use App\Models\UserWorkspace;
use App\Models\WorkspaceWorkerRequirement;
use App\Services\Company\BusinessOnboardingService;
use App\Jobs\Matching\GenerateMatchesForPod;
use App\Services\Pod\UnauthorizedPodMutation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** UNA-014: POD mode/threshold configuration, company defaults, forms and after-commit recalculation. */
class PodMatchingConfigurationTest extends WorkflowTestCase
{
    public function test_the_pod_mode_enum_has_three_modes_and_maps_company_preferences(): void
    {
        $this->assertSame(['recommend_only', 'pending_approval', 'automatic'], PodMatchingMode::values());
        $this->assertFalse(PodMatchingMode::RECOMMEND_ONLY->actsOnMemberships());
        $this->assertSame([PodMatchingMode::RECOMMEND_ONLY, PodMatchingMode::PENDING_APPROVAL, PodMatchingMode::AUTOMATIC, null], [
            PodMatchingMode::fromCompanyPreference(MatchingMode::MANUAL),
            PodMatchingMode::fromCompanyPreference(MatchingMode::ASSISTED),
            PodMatchingMode::fromCompanyPreference(MatchingMode::AUTOMATIC),
            PodMatchingMode::fromCompanyPreference(null),
        ]);

        // Rows stored before UNA-014 stay valid.
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Legacy']);
        foreach (['automatic' => PodMatchingMode::AUTOMATIC, 'pending_approval' => PodMatchingMode::PENDING_APPROVAL] as $raw => $mode) {
            DB::table('pods')->where('id', $pod->id)->update(['matching_mode' => $raw]);
            $this->assertSame($mode, Pod::find($pod->id)->matching_mode);
        }
    }

    public function test_new_pods_snapshot_company_defaults_which_never_rewrite_existing_pods(): void
    {
        $this->assertSame(70, config('pod_matching.default_minimum_threshold'));
        $this->assertSame(70, $this->pods()->createDraft($this->company, ['title' => 'Fallback'])->minimum_match_threshold, 'no company default: config');

        app(BusinessOnboardingService::class)->save($this->company, ['matching_mode' => 'manual', 'default_minimum_match_threshold' => 55]);
        $this->assertSame(['manual', 55], [$this->company->ownWorkspace()->matching_mode->value, $this->company->ownWorkspace()->default_minimum_match_threshold]);
        $first = $this->pods()->createDraft($this->company, ['title' => 'First']);
        $this->assertSame([PodMatchingMode::RECOMMEND_ONLY, 55], [$first->matching_mode, $first->minimum_match_threshold]);
        $this->assertSame([PodMatchingMode::RECOMMEND_ONLY, 55], [$this->pods()->companyDefaults($this->company)['pod_matching_mode'], $this->pods()->companyDefaults($this->company)['minimum_match_threshold']]);

        $before = DB::table('pods')->orderBy('id')->get();
        app(BusinessOnboardingService::class)->save($this->company, ['matching_mode' => 'automatic', 'default_minimum_match_threshold' => 90]);
        $this->assertEquals($before, DB::table('pods')->orderBy('id')->get(), 'existing PODs untouched');

        $second = $this->pods()->createDraft($this->company, ['title' => 'Second']);
        $this->assertSame([PodMatchingMode::AUTOMATIC, 90], [$second->matching_mode, $second->minimum_match_threshold]);

        // Explicit values override the defaults, on creation and later.
        $third = $this->pods()->createDraft($this->company, ['title' => 'Third', 'matching_mode' => 'recommend_only', 'minimum_match_threshold' => 0]);
        $this->assertSame([PodMatchingMode::RECOMMEND_ONLY, 0], [$third->matching_mode, $third->minimum_match_threshold]);
        $third = $this->pods()->updateDraft($third, $this->company, ['matching_mode' => 'pending_approval', 'minimum_match_threshold' => 100]);
        $this->assertSame([PodMatchingMode::PENDING_APPROVAL, 100], [$third->matching_mode, $third->minimum_match_threshold]);
    }

    public function test_the_threshold_is_validated_by_the_request_and_the_domain(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Validated']);
        $post = fn (array $data) => $this->actingAs($this->company)->post(route('pods.setup.update', $pod), $data + ['matching_present' => 1]);

        foreach ([0, 100, '45'] as $valid) {
            $post(['minimum_match_threshold' => $valid])->assertSessionHasNoErrors();
            $this->assertSame((int) $valid, $pod->fresh()->minimum_match_threshold);
        }
        foreach (['' => 'missing', '-1' => 'negative', '101' => 'too high', '7.5' => 'fraction', 'abc' => 'text'] as $invalid => $why) {
            $post(['minimum_match_threshold' => $invalid])->assertSessionHasErrors('minimum_match_threshold');
            $this->assertSame(45, $pod->fresh()->minimum_match_threshold, $why);
        }
        $post([])->assertSessionHasErrors('minimum_match_threshold');
        $post(['minimum_match_threshold' => 50, 'matching_mode' => 'sometimes'])->assertSessionHasErrors('matching_mode');

        foreach ([-1, 101, '7.5', null, true] as $invalid) {
            try {
                $this->pods()->updateDraft($pod, $this->company, ['minimum_match_threshold' => $invalid]);
                $this->fail('saved ' . var_export($invalid, true));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('minimum_match_threshold', $e->errors());
            }
        }
        $this->assertSame(45, $pod->fresh()->minimum_match_threshold);

        $this->actingAs($this->company)->post(route('company.onboarding.update'), ['step' => 'matching', 'default_minimum_match_threshold' => 101])
            ->assertSessionHasErrors('default_minimum_match_threshold');
    }

    public function test_the_matching_section_needs_a_mode_and_a_valid_threshold(): void
    {
        $service = app(\App\Services\Pod\PodConfigurationService::class);
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Section']);
        $this->assertFalse($service->breakdown($pod)['sections']['matching']['complete']);

        $pod = $this->pods()->updateDraft($pod, $this->company, ['matching_mode' => 'recommend_only']);
        $this->assertTrue($service->breakdown($pod)['sections']['matching']['complete']);

        $pod->forceFill(['minimum_match_threshold' => 150]);
        $this->assertSame(['Set a minimum match score from 0 to 100'], $service->breakdown($pod)['sections']['matching']['missing']);
    }

    public function test_another_company_cannot_change_the_configuration(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Mine', 'minimum_match_threshold' => 70]);
        $other = $this->account('company');

        $this->actingAs($other)->post(route('pods.setup.update', $pod), ['matching_present' => 1, 'minimum_match_threshold' => 1, 'matching_mode' => 'automatic'])->assertForbidden();
        try {
            $this->pods()->updateDraft($pod, $other, ['minimum_match_threshold' => 1]);
            $this->fail('updated');
        } catch (UnauthorizedPodMutation) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame([70, null], [$pod->fresh()->minimum_match_threshold, $pod->fresh()->matching_mode]);
    }

    public function test_forms_show_every_mode_the_threshold_explanations_and_saved_values(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Form', 'matching_mode' => 'automatic', 'minimum_match_threshold' => 64]);

        $page = $this->actingAs($this->company)->get(route('pods.setup.edit', ['pod' => $pod, 'step' => 'matching']))->assertOk();
        foreach (PodMatchingMode::cases() as $mode) {
            $page->assertSee('value="' . $mode->value . '"', false)->assertSee($mode->label())->assertSee($mode->description());
        }
        $page->assertSee('id="pod-matching-automatic" value="automatic" autocomplete="off" data-pod-matching-mode checked', false)
            ->assertSee('name="minimum_match_threshold"', false)
            ->assertSee('value="64"', false)
            ->assertSee('name="matching_present" value="1"', false)
            ->assertSee(__('Automatic mode places qualified candidates directly in the Outer Circle of this published POD, without asking you first. They get Outer Circle access to the POD workspace. Nobody is ever moved to the Inner Circle automatically.'))
            ->assertSee('(failure disqualifies; only configured criteria can be mandatory)', false)
            ->assertDontSee('Nobody joins automatically');

        $this->company->ownWorkspace()->forceFill(['matching_mode' => 'assisted', 'default_minimum_match_threshold' => 81])->save();
        $this->actingAs($this->company)->get(route('company.onboarding.edit', ['step' => 'matching']))->assertOk()
            ->assertSee('name="default_minimum_match_threshold"', false)
            ->assertSee('value="81"', false)
            ->assertSee('changing these defaults never updates existing PODs', false);
    }

    // ------------------------------------------------------------------ recalculation (queued since UNA-016)

    public function test_publishing_queues_generation_after_commit(): void
    {
        $this->completeBusinessProfile($this->company);
        $pod = $this->configuredDraft(); // pending_approval, Backend Developer + PHP/SQL
        $candidate = $this->qualifiedEmployee();
        $candidate->profile->forceFill(['job_title_id' => $this->role->id])->save();
        $requirement = $pod->workerRequirements->sole();

        $this->workflow()->evaluateAndApply($requirement, $candidate->fresh(), $this->at());
        $this->assertSame(0, PodUser::where('user_id', $candidate->id)->count(), 'draft: score only');
        $this->assertSame(0, $this->podJobsFor($pod->id), 'draft edits queue nothing');

        $this->pods()->publish($pod, $this->company);

        $this->assertSame(1, $this->podJobsFor($pod->id));
        $this->assertSame(0, PodUser::where('user_id', $candidate->id)->count(), 'nothing runs in the request');
        $this->drainMatchingJobs();
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $candidate->id)->sole()->state);
    }

    public function test_lowering_the_threshold_queues_generation_that_acts_forward(): void
    {
        $pod = $this->configurePod('pending_approval', 70);
        $requirement = $this->partialRequirement(); // 68.75
        $candidate = $this->qualifiedEmployee();
        $this->apply($requirement, $candidate);
        $this->assertSame(0, PodUser::where('user_id', $candidate->id)->count());

        $this->pods()->updateDraft($pod, $this->company, ['minimum_match_threshold' => 65]);
        $this->assertSame(1, $this->podJobsFor($pod->id));
        $this->drainMatchingJobs();

        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $candidate->id)->sole()->state);
    }

    public function test_raising_the_threshold_or_lowering_scores_never_demotes_or_removes(): void
    {
        $pod = $this->configurePod('automatic', 70);
        $requirement = $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();
        $this->apply($requirement, $candidate);
        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $candidate->id)->sole()->state);
        $before = $this->memberships();

        $this->pods()->updateDraft($pod, $this->company, [
            'minimum_match_threshold' => 100,
            'requirements_present' => 1,
            'requirements' => [$this->requirementRow($requirement, ['skill_ids' => [$this->php->id, $this->docker->id]])],
        ]);
        $this->drainMatchingJobs();

        $match = PodMatch::sole();
        $this->assertSame(['68.75', MatchStatus::ELIGIBLE], [$match->score, $match->status], 're-scored');
        $this->assertEquals($before, $this->memberships(), 'still Outer Circle with the same access');
        $this->assertSame(1, (int) UserWorkspace::where('workspace_id', $pod->workspace->id)->where('user_id', $candidate->id)->value('is_active'));

        // Switching to recommend-only does not undo the placement either.
        $this->pods()->updateDraft($pod, $this->company, ['matching_mode' => 'recommend_only']);
        $this->drainMatchingJobs();
        $this->assertEquals($before, $this->memberships());
    }

    public function test_switching_pending_to_automatic_advances_still_qualified_applicants(): void
    {
        $pod = $this->configurePod('pending_approval');
        $requirement = $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();
        $this->apply($requirement, $candidate);

        $this->pods()->updateDraft($pod, $this->company, ['matching_mode' => 'automatic']);
        $this->drainMatchingJobs();

        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $candidate->id)->sole()->state);
    }

    public function test_requirement_criteria_and_mandatory_changes_regenerate_matches(): void
    {
        $pod = $this->configurePod('recommend_only', 70);
        $requirement = $this->perfectRequirement();
        $other = $this->perfectRequirement();
        $candidate = $this->qualifiedEmployee();
        $this->apply($requirement, $candidate);
        $this->apply($other, $candidate);

        $this->pods()->updateDraft($pod, $this->company, ['requirements_present' => 1, 'requirements' => [
            $this->requirementRow($requirement, ['skill_ids' => [$this->php->id, $this->docker->id]]),
            $this->requirementRow($other),
        ]]);
        $this->assertSame(1, $this->podJobsFor($pod->id));
        $this->drainMatchingJobs();
        $this->assertSame(['68.75', '100.00'], [
            PodMatch::where('pod_worker_requirement_id', $requirement->id)->value('score'),
            PodMatch::where('pod_worker_requirement_id', $other->id)->value('score'),
        ]);

        // A new mandatory criterion the candidate fails disqualifies the stored match.
        $this->pods()->updateDraft($pod, $this->company, ['requirements_present' => 1, 'requirements' => [
            $this->requirementRow($requirement),
            $this->requirementRow($other, ['project_type' => 'hourly', 'mandatory_criteria' => ['project_type']]),
        ]]);
        $this->drainMatchingJobs();
        $match = PodMatch::where('pod_worker_requirement_id', $other->id)->sole()->load('reasons');
        $this->assertSame(MatchStatus::DISQUALIFIED, $match->status);
        $this->assertTrue($match->reasons->firstWhere('criterion', \App\Enums\Matching\MatchCriterion::PROJECT_TYPE)->disqualifying);
    }

    public function test_inactivating_a_requirement_removes_its_match_snapshots(): void
    {
        $pod = $this->configurePod('recommend_only', 0);
        $requirement = $this->perfectRequirement();
        $this->apply($requirement, $this->qualifiedEmployee());
        $this->apply($requirement, $this->qualifiedEmployee());
        $this->assertSame(2, PodMatch::count());

        $this->pods()->updateDraft($pod, $this->company, ['requirements_present' => 1, 'requirements' => [$this->requirementRow($requirement, ['is_active' => 0])]]);

        $this->assertSame(0, PodMatch::count(), 'no stale qualified recommendation remains (same transaction)');
        $this->drainMatchingJobs();
        $this->assertSame(0, PodMatch::count(), 'the POD has no active requirement left to generate');
        $this->expectException(\App\Services\Matching\InactiveRequirement::class);
        $this->apply($requirement, $this->qualifiedEmployee());
    }

    public function test_unrelated_edits_and_template_edits_queue_nothing(): void
    {
        $workspace = $this->completeBusinessProfile($this->company);
        $template = WorkspaceWorkerRequirement::where('workspace_id', $workspace->id)->sole();
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Imported', 'import_template_ids' => [$template->id]]);
        $pod->forceFill(['status' => PodStatus::PUBLISHED, 'matching_mode' => 'recommend_only'])->save();
        $requirement = $pod->workerRequirements->sole();
        $this->apply($requirement, $this->qualifiedEmployee());
        $evaluatedAt = PodMatch::sole()->evaluated_at;

        $this->pods()->updateDraft($pod, $this->company, ['title' => 'Renamed', 'description' => 'New words', 'skills' => [$this->sql->id]]);
        $this->pods()->updateDraft($pod, $this->company, ['requirements_present' => 1, 'requirements' => [$this->requirementRow($requirement, ['headcount' => 9, 'description' => 'More'])]]);
        app(BusinessOnboardingService::class)->save($this->company, ['requirements_present' => 1, 'requirements' => [[
            'id' => $template->id, 'job_title_id' => $template->job_title_id, 'headcount' => 2, 'skill_ids' => [$this->sql->id],
            'preferred_work_mode' => 'onsite', 'preferred_engagement_type' => 'contract', 'is_active' => 1,
        ]]]);

        Bus::assertNotDispatched(GenerateMatchesForPod::class);
        $this->assertEquals($evaluatedAt, PodMatch::sole()->evaluated_at);
        $this->assertSame([$this->php->id], $requirement->fresh()->skills->pluck('id')->all(), 'POD snapshot unchanged');
    }

    public function test_generation_is_queued_only_after_commit_and_never_on_rollback(): void
    {
        $pod = $this->configurePod('pending_approval', 70);
        $this->apply($this->partialRequirement(), $candidate = $this->qualifiedEmployee());

        try {
            DB::transaction(function () use ($pod) {
                $this->pods()->updateDraft($pod, $this->company, ['minimum_match_threshold' => 50]);
                Bus::assertNotDispatched(GenerateMatchesForPod::class);
                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }

        Bus::assertNotDispatched(GenerateMatchesForPod::class);
        $this->assertSame(70, $pod->fresh()->minimum_match_threshold);

        DB::transaction(fn () => $this->pods()->updateDraft($pod, $this->company, ['minimum_match_threshold' => 50]));
        $this->assertSame(1, $this->podJobsFor($pod->id), 'once, after the outer commit');
        $this->drainMatchingJobs();
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $candidate->id)->sole()->state);
    }
}

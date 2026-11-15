<?php

namespace Tests\Feature\Pod;

use App\Enums\Pod\PodMatchingMode;
use App\Enums\Pod\PodStatus;
use App\Enums\PodUser\State;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\PodWorkerRequirement;
use App\Models\UserWorkspace;
use App\Models\WorkspaceWorkerRequirement;
use App\Services\Pod\UnauthorizedPodMutation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** UNA-011: guided draft creation and configuration (PodService + /pods/create, /pods/{pod}/setup). */
class PodDraftLifecycleTest extends PodTestCase
{
    public function test_an_incomplete_company_creates_a_draft_with_only_a_title_atomically(): void
    {
        $this->actingAs($this->company)->get(route('pods.create'))->assertOk()->assertSee('name="draft_token"', false);
        $this->assertSame(0, Pod::count(), 'opening the start page creates nothing');

        $this->actingAs($this->company)->post(route('pods.drafts.store'), ['title' => 'Data Team', 'draft_token' => 'tok-1'])
            ->assertSessionHasNoErrors();
        $pod = Pod::sole();
        $this->assertSame([PodStatus::DRAFT, null, 'Data Team'], [$pod->status, $pod->published_at, $pod->title]);
        $this->assertNotNull($pod->configuration_started_at);

        // POD workspace + both owner memberships + exactly one initial history row.
        $this->assertNotNull($workspace = $pod->workspace);
        $membership = PodUser::where('pod_id', $pod->id)->sole();
        $this->assertSame(['Owner', State::INNER_CIRCLE], [$membership->permission, $membership->state]);
        $this->assertSame(1, DB::table('pod_user_state_histories')->where('pod_user_id', $membership->id)->count());
        $this->assertSame(1, UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $this->company->id)->count());

        // A resubmitted form resumes the same draft instead of creating another.
        $this->actingAs($this->company)->post(route('pods.drafts.store'), ['title' => 'Data Team', 'draft_token' => 'tok-1'])
            ->assertRedirect(route('pods.setup.edit', ['pod' => $pod, 'step' => 'basics']));
        $this->assertSame(1, Pod::count());

        $this->actingAs($this->company)->post(route('pods.drafts.store'), ['title' => ''])->assertSessionHasErrors('title');
        $this->assertSame(1, Pod::count());
    }

    public function test_only_companies_create_drafts(): void
    {
        foreach (['employee', 'administrator'] as $type) {
            $account = $this->account($type);
            $this->actingAs($account)->get(route('pods.create'))->assertForbidden();
            $this->actingAs($account)->post(route('pods.drafts.store'), ['title' => 'X'])->assertForbidden();
        }
        $this->expectException(UnauthorizedPodMutation::class);
        $this->pods()->createDraft($this->account('employee'), ['title' => 'X']);
    }

    public function test_failed_creation_rolls_everything_back_and_removes_the_upload(): void
    {
        $template = $this->template();
        $before = $this->snapshot();
        DB::statement("CREATE TRIGGER fail_history BEFORE INSERT ON pod_user_state_histories BEGIN SELECT RAISE(ABORT, 'injected failure'); END");

        try {
            $this->pods()->createDraft($this->company, $this->completeConfiguration() + ['import_template_ids' => [$template->id]], UploadedFile::fake()->image('pod.png'));
            $this->fail('created');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'uploaded image and resized copies removed');
    }

    public function test_drafts_save_partial_progress_and_configuration_is_transactional(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Draft']);

        $this->save($pod, ['description' => 'Partial', 'step' => 'basics', 'intent' => 'continue'])
            ->assertRedirect(route('pods.setup.edit', ['pod' => $pod->id, 'step' => 'skills']))->assertSessionHasNoErrors();
        $this->save($pod, ['skills_present' => 1, 'skills' => [$this->php->id], 'matching_mode' => 'automatic'])->assertSessionHasNoErrors();
        $pod->refresh();
        $this->assertSame(['Partial', PodMatchingMode::AUTOMATIC, [$this->php->id]], [$pod->description, $pod->matching_mode, $pod->skills->pluck('id')->all()]);

        // A failure while writing requirement skills leaves the whole save unapplied.
        $before = $this->snapshot();
        DB::statement("CREATE TRIGGER fail_req_skill BEFORE INSERT ON pod_worker_requirement_skill BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
        try {
            $this->pods()->updateDraft($pod, $this->company, ['title' => 'Renamed'] + $this->completeConfiguration(), UploadedFile::fake()->image('x.png'));
            $this->fail('saved');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        DB::statement('DROP TRIGGER fail_req_skill');
        $this->assertEquals($before, $this->snapshot());
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Requirements sync by id and delete the rest.
        $this->save($pod, ['requirements_present' => 1] + array_intersect_key($this->completeConfiguration(), ['requirements' => 1]))->assertSessionHasNoErrors();
        $requirement = PodWorkerRequirement::where('pod_id', $pod->id)->sole();
        $this->assertEqualsCanonicalizing([$this->php->id, $this->sql->id], $requirement->skills->pluck('id')->all());
        $this->save($pod, ['requirements_present' => 1, 'requirements' => [['id' => $requirement->id, 'job_title_id' => $this->role->id, 'headcount' => 5, 'skill_ids' => [$this->sql->id]]]])->assertSessionHasNoErrors();
        $this->assertSame([5, [$this->sql->id]], [$requirement->fresh()->headcount, $requirement->fresh()->skills->pluck('id')->all()]);
        $this->save($pod, ['requirements_present' => 1])->assertSessionHasNoErrors();
        $this->assertSame([0, 0], [PodWorkerRequirement::count(), DB::table('pod_worker_requirement_skill')->count()]);
    }

    public function test_image_replacement_deletes_the_old_file_only_after_commit(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Draft'], UploadedFile::fake()->image('first.png'));
        $first = $pod->image;
        Storage::disk('public')->assertExists($first);

        $this->save($pod, ['image' => UploadedFile::fake()->image('second.png')])->assertSessionHasNoErrors();
        $second = $pod->fresh()->image;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_imported_company_requirements_are_snapshots(): void
    {
        $template = $this->template();
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Draft']);

        $this->save($pod, ['import_template_ids' => [$template->id]])->assertSessionHasNoErrors();
        $copy = PodWorkerRequirement::where('pod_id', $pod->id)->sole();
        $this->assertSame([$template->id, $this->role->id, 3, 'hybrid', [$this->php->id]],
            [$copy->source_workspace_requirement_id, $copy->job_title_id, $copy->headcount, $copy->preferred_work_mode->value, $copy->skills->pluck('id')->all()]);

        // Editing the POD copy never changes the company template...
        $this->save($pod, ['requirements_present' => 1, 'requirements' => [['id' => $copy->id, 'source_workspace_requirement_id' => $template->id, 'job_title_id' => $this->role->id, 'headcount' => 9, 'skill_ids' => [$this->sql->id]]]])
            ->assertSessionHasNoErrors();
        $this->assertSame([3, [$this->php->id]], [$template->fresh()->headcount, $template->fresh()->skills->pluck('id')->all()]);
        // ...and editing or deleting the template never changes the POD.
        $template->forceFill(['headcount' => 1])->save();
        $template->skills()->detach();
        $template->delete();
        $this->assertSame([9, [$this->sql->id], null], [$copy->fresh()->headcount, $copy->fresh()->skills->pluck('id')->all(), $copy->fresh()->source_workspace_requirement_id]);
    }

    public function test_other_accounts_cannot_change_or_publish_a_draft_and_foreign_ids_are_rejected(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Mine']);
        $other = $this->account('company');
        $theirTemplate = new WorkspaceWorkerRequirement(['job_title_id' => $this->role->id, 'headcount' => 1]);
        $theirTemplate->forceFill(['workspace_id' => $other->ownWorkspace()->id])->save();
        $theirPod = $this->pods()->createDraft($other, $this->completeConfiguration());
        $theirRequirement = PodWorkerRequirement::where('pod_id', $theirPod->id)->sole();

        foreach ([$other, $this->account('employee'), $this->account('administrator')] as $account) {
            $this->actingAs($account)->get(route('pods.setup.edit', $pod))->assertForbidden();
            $this->actingAs($account)->post(route('pods.setup.update', $pod), ['title' => 'Hijacked'])->assertForbidden();
            $this->actingAs($account)->post(route('pods.publish', $pod))->assertForbidden();
        }
        $this->assertSame('Mine', $pod->fresh()->title);

        $this->save($pod, ['import_template_ids' => [$theirTemplate->id]])->assertSessionHasErrors('import_template_ids.0');
        $this->save($pod, ['requirements_present' => 1, 'requirements' => [['id' => $theirRequirement->id, 'headcount' => 9]]])->assertSessionHasErrors('requirements.0.id');
        $this->save($pod, ['created_by' => $other->id, 'status' => 'published', 'published_at' => now(), 'pod_id' => $theirPod->id, 'title' => 'Still mine'])->assertSessionHasNoErrors();
        $pod->refresh();
        $this->assertSame([$this->company->id, PodStatus::DRAFT, null, 'Still mine'], [(int) $pod->created_by, $pod->status, $pod->published_at, $pod->title]);
        $this->assertSame(2, $theirRequirement->fresh()->headcount);
    }

    public function test_matching_mode_is_preselected_from_the_company_and_selecting_automatic_changes_no_membership(): void
    {
        $this->company->ownWorkspace()->forceFill(['matching_mode' => 'automatic'])->save();
        $this->assertSame(PodMatchingMode::AUTOMATIC, $this->pods()->createDraft($this->company, ['title' => 'A'])->matching_mode);
        $this->company->ownWorkspace()->forceFill(['matching_mode' => 'assisted'])->save();
        $this->assertSame(PodMatchingMode::PENDING_APPROVAL, $this->pods()->createDraft($this->company, ['title' => 'B'])->matching_mode);
        $this->company->ownWorkspace()->forceFill(['matching_mode' => null])->save();
        $pod = $this->pods()->createDraft($this->company, ['title' => 'C']);
        $this->assertNull($pod->matching_mode);

        // A POD with an applicant: switching to automatic never moves anyone.
        $applicant = $this->account('employee');
        $this->completeEmployeeProfile($applicant);
        app(\App\Services\PodUserService::class)->requestJoinPod($applicant->id, $pod->id);
        $before = $this->snapshot(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'notifications']);

        $this->save($pod, ['matching_mode' => 'automatic'])->assertSessionHasNoErrors();
        $this->assertSame(PodMatchingMode::AUTOMATIC, $pod->fresh()->matching_mode);
        $this->save($pod, ['matching_mode' => 'pending_approval'])->assertSessionHasNoErrors();
        $this->assertSame(PodMatchingMode::PENDING_APPROVAL, $pod->fresh()->matching_mode);
        $this->assertEquals($before, $this->snapshot(['pod_users', 'pod_user_state_histories', 'user_workspaces', 'notifications']));
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $applicant->id)->sole()->state);
    }

    public function test_setup_page_and_management_page_show_draft_progress(): void
    {
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Draft POD', 'skills' => [$this->php->id]]);
        $this->template();

        $this->actingAs($this->company)->get(route('pods.setup.edit', ['pod' => $pod, 'step' => 'requirements']))->assertOk()
            ->assertSee('data-pod-status="draft"', false)
            ->assertSee('35<small>%</small>', false)
            ->assertSee('data-co-step="review"', false)
            ->assertSee('data-pod-import', false)
            ->assertSee(route('pods.publish', $pod), false)
            ->assertSee('assets/custom/js/pod-onboarding.js', false);

        $page = $this->actingAs($this->company)->get(route('pods.index'))->assertOk();
        $page->assertSee('data-pod-draft="' . $pod->id . '"', false)
            ->assertSee(__(':percentage% configured', ['percentage' => 35]))
            ->assertSee(route('pods.setup.edit', $pod), false)
            ->assertSee('href="' . route('pods.create') . '"', false)
            ->assertDontSee('data-url="' . route('pods.create') . '"', false);
        $this->assertSame(1, substr_count($page->getContent(), 'axios.min.js'));
    }

    // ================================================================ helpers

    private function template(): WorkspaceWorkerRequirement
    {
        $template = new WorkspaceWorkerRequirement([
            'job_title_id' => $this->role->id, 'headcount' => 3, 'preferred_work_mode' => 'hybrid', 'preferred_engagement_type' => 'full_time', 'is_active' => true,
        ]);
        $template->forceFill(['workspace_id' => $this->company->ownWorkspace()->id])->save();
        $template->skills()->sync([$this->php->id]);

        return $template;
    }

    private function save(Pod $pod, array $data)
    {
        return $this->actingAs($this->company)->post(route('pods.setup.update', $pod), $data);
    }
}

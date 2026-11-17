<?php

namespace Tests\Feature\Pod;

use App\Enums\Pod\PodStatus;
use App\Models\Pod;
use App\Models\PodWorkerRequirement;
use App\Services\Pod\IncompleteBusinessOnboarding;
use App\Services\Pod\IncompletePodConfiguration;
use App\Services\Pod\PodAlreadyPublished;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** UNA-011: publication is a separate, re-checked, idempotent operation; published PODs stay complete. */
class PodPublicationTest extends PodTestCase
{
    public function test_a_fully_configured_pod_cannot_publish_while_the_company_profile_is_incomplete(): void
    {
        $pod = $this->configuredDraft();
        $before = $this->snapshot();

        $response = $this->actingAs($this->company)->postJson(route('pods.publish', $pod))->assertStatus(422);
        $response->assertJsonPath('pod_completion_percentage', 100)
            ->assertJsonPath('required_pod_percentage', 100)
            ->assertJsonPath('missing_pod_requirements', [])
            ->assertJsonPath('company_completion_percentage', 0)
            ->assertJsonPath('required_company_percentage', 100)
            ->assertJsonPath('setup_url', route('pods.setup.edit', $pod))
            ->assertJsonPath('onboarding_url', route('company.onboarding.edit'));
        $this->assertNotEmpty($response->json('missing_company_requirements'));
        $this->assertEquals($before, $this->snapshot());

        $this->expectException(IncompleteBusinessOnboarding::class);
        $this->pods()->publish($pod, $this->company);
    }

    public function test_a_complete_company_cannot_publish_an_incomplete_pod(): void
    {
        $this->completeBusinessProfile($this->company);
        $pod = $this->pods()->createDraft($this->company->fresh(), ['title' => 'Half', 'skills' => [$this->php->id]]);

        $this->actingAs($this->company)->post(route('pods.publish', $pod))
            ->assertRedirect(route('pods.setup.edit', ['pod' => $pod, 'step' => 'review']))
            // 55%: title + POD skill, plus the matching mode preselected from the complete company profile.
            ->assertSessionHas('error', __('Complete the POD setup before publishing. It is :percentage% configured; :required% is required.', ['percentage' => 55, 'required' => 100]));
        $this->assertTrue($pod->fresh()->isDraft());

        $this->expectException(IncompletePodConfiguration::class);
        $this->pods()->publish($pod, $this->company->fresh());
    }

    public function test_a_complete_company_publishes_a_complete_pod_once(): void
    {
        $this->completeBusinessProfile($this->company);
        $pod = $this->configuredDraft($this->company->fresh());
        $counts = fn () => [DB::table('pod_users')->count(), DB::table('pod_user_state_histories')->count(), DB::table('user_workspaces')->count(), DB::table('notifications')->count(), DB::table('workspaces')->count()];
        $before = $counts();

        $this->actingAs($this->company)->post(route('pods.publish', $pod))->assertRedirect(route('pods.index'))->assertSessionHas('success');
        $published = $pod->fresh();
        $this->assertSame(PodStatus::PUBLISHED, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertEquals($published->published_at, $published->configuration_completed_at);

        // Repeated publication changes nothing and has no effects.
        $this->actingAs($this->company)->postJson(route('pods.publish', $pod))->assertStatus(409)->assertJsonPath('status', 'published');
        try {
            $this->pods()->publish($pod, $this->company->fresh());
            $this->fail('published twice');
        } catch (PodAlreadyPublished) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame($before, $counts());
        $this->assertEquals($published->published_at, $pod->fresh()->published_at);
        // UNA-016: only the first publication queues match generation; nothing else is dispatched.
        Bus::assertDispatchedTimes(\App\Jobs\Matching\GenerateMatchesForPod::class, 1);
        Bus::assertNotDispatched(\App\Jobs\SendEmail::class);
        Mail::assertNothingSent();

        // The publish form is gone once published.
        $this->actingAs($this->company)->get(route('pods.setup.edit', $pod))->assertOk()->assertDontSee(route('pods.publish', $pod), false);
    }

    public function test_a_published_pod_cannot_be_edited_into_an_incomplete_configuration(): void
    {
        $this->completeBusinessProfile($this->company);
        $pod = $this->configuredDraft($this->company->fresh());
        $this->pods()->publish($pod, $this->company->fresh());
        $before = $this->snapshot(['pods', 'pods_skills', 'pod_worker_requirements', 'pod_worker_requirement_skill']);

        // Guided setup.
        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), ['skills_present' => 1, 'description' => 'short'])
            ->assertSessionHas('error', __('This change would leave the published POD incomplete, so it was not saved.'));
        // Existing published-POD edit path (API).
        $this->actingAs($this->company)->postJson('/api/pod/' . $pod->id, ['title' => ''])->assertStatus(422)
            ->assertJsonPath('pod_completion_percentage', 85);
        $this->assertEquals($before, $this->snapshot(['pods', 'pods_skills', 'pod_worker_requirements', 'pod_worker_requirement_skill']));
        $this->assertTrue($pod->fresh()->isPublished());

        // Valid edits still save.
        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), ['title' => 'Renamed Platform Team'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Platform Team', $pod->fresh()->title);
        $this->assertSame('Renamed Platform Team-workspace', $pod->fresh()->workspace->name);
    }

    public function test_existing_incomplete_published_pods_can_still_be_edited_without_regressing(): void
    {
        // A POD published before UNA-011 (no requirements or matching mode).
        $pod = $this->pods()->createDraft($this->company, ['title' => 'Legacy', 'skills' => [$this->php->id]]);
        Pod::whereKey($pod->id)->update(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), ['matching_mode' => 'automatic'])->assertSessionHasNoErrors();
        $this->assertSame('automatic', $pod->fresh()->matching_mode->value);
        $this->actingAs($this->company)->post(route('pods.setup.update', $pod), ['skills_present' => 1])->assertSessionHas('error');
        $this->assertCount(1, $pod->fresh()->skills);
        $this->assertSame(0, PodWorkerRequirement::count());
    }
}

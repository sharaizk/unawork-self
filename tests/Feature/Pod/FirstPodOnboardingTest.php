<?php

namespace Tests\Feature\Pod;

use App\Models\Pod;

/** UNA-011: the company-onboarding review step offers create / resume / manage for the first POD. */
class FirstPodOnboardingTest extends PodTestCase
{
    public function test_review_step_shows_create_resume_and_manage_states_without_creating_drafts(): void
    {
        $page = fn () => $this->actingAs($this->company)->get(route('company.onboarding.edit', ['step' => 'review']))->assertOk();

        $page()->assertSee('data-first-pod="none"', false)->assertSee(__('Create your first POD'))->assertSee(route('pods.create'), false);
        $page();
        $this->assertSame(0, Pod::count(), 'refreshing the onboarding page creates no draft');

        $older = $this->pods()->createDraft($this->company, ['title' => 'Older draft']);
        $newer = $this->pods()->createDraft($this->company, ['title' => 'Newer draft']);
        Pod::whereKey($older->id)->update(['updated_at' => now()->addMinute()]);
        $page()->assertSee('data-first-pod="draft"', false)
            ->assertSee(__('Resume POD setup'))
            ->assertSee('href="' . route('pods.setup.edit', $older) . '"', false);
        $page();
        $this->assertSame(2, Pod::count());

        $this->completeBusinessProfile($this->company);
        $this->pods()->updateDraft($newer, $this->company->fresh(), $this->completeConfiguration());
        $this->pods()->publish($newer, $this->company->fresh());
        $page()->assertSee('data-first-pod="published"', false)
            ->assertSee(__('Your POD :title is published.', ['title' => 'Platform Team']))
            ->assertSee(route('pods.index'), false);

        // The company score does not depend on having a POD.
        $this->assertSame(100, app(\App\Services\Company\BusinessOnboardingCompletionService::class)->percentage($this->company->ownWorkspace()));
    }
}

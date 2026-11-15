<?php

namespace App\Http\Controllers;

use App\Enums\Company\ApprovalMode;
use App\Enums\Company\MatchingMode;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Http\Requests\Company\UpdateBusinessOnboardingRequest;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Skill;
use App\Models\Utility;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Services\Company\BusinessOnboardingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guided company onboarding / Company Profile at /company/onboarding (UNA-010). Persistence lives in
 * BusinessOnboardingService and scoring in BusinessOnboardingCompletionService.
 */
class CompanyOnboardingController extends Controller
{
    public function __construct(
        private BusinessOnboardingService $onboarding,
        private BusinessOnboardingCompletionService $completion,
    ) {
    }

    public function edit(Request $request)
    {
        $workspace = $this->completion->companyWorkspace($request->user());
        abort_if($workspace === null, 403, __('Only a company can manage a company profile.'));
        $this->authorize('manageCompanyProfile', $workspace);

        $this->onboarding->markStarted($workspace);
        $workspace->load(['industry', 'workerRequirements.skills']);
        $breakdown = $this->completion->breakdown($workspace);
        $step = $request->query('step', $workspace->business_onboarding_step ?: 'company');

        // UNA-011: first-POD card. Deterministic lookup; viewing this page never creates a draft.
        $user = $request->user();
        $publishedPod = \App\Models\Pod::published()->where('created_by', $user->id)->orderBy('published_at')->orderBy('id')->first();
        $draftPod = $publishedPod ? null : \App\Models\Pod::drafts()->where('created_by', $user->id)->latest('updated_at')->orderByDesc('id')->first();

        return view('company.onboarding', [
            'publishedPod' => $publishedPod,
            'draftPod' => $draftPod,
            'draftPodPercentage' => $draftPod ? app(\App\Services\Pod\PodConfigurationService::class)->percentage($draftPod) : null,
            'workspace' => $workspace,
            'breakdown' => $breakdown,
            'step' => in_array($step, BusinessOnboardingService::STEPS, true) ? $step : 'company',
            'steps' => BusinessOnboardingService::STEPS,
            'logoUrl' => $workspace->logo ? Utility::get_file(BusinessOnboardingService::LOGO_DIR . '/') . $workspace->logo : null,
            'industries' => Industry::query()->alphabetical()->get(['id', 'name']),
            // Industry tags drive the client-side prioritizing of the selectors (data-industries).
            'jobTitles' => JobTitle::query()->with('industries:id')->alphabetical()->get(['id', 'title']),
            'skills' => Skill::query()->with('industries:id')->alphabetical()->get(['id', 'name']),
            'matchingModes' => MatchingMode::cases(),
            'approvalModes' => ApprovalMode::options(),
            'proficiencies' => SkillProficiency::options(),
            'workModes' => WorkMode::options(),
            'engagementTypes' => EngagementType::options(),
        ]);
    }

    public function update(UpdateBusinessOnboardingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $step = $data['step'] ?? null;

        try {
            $workspace = $this->onboarding->save($request->user(), $data, $request->file('logo'), $step);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withInput($request->except('logo'))
                ->with('error', __('Your company profile could not be saved. Please try again.'));
        }

        $breakdown = $this->completion->breakdown($workspace);
        $next = $step;
        if (($data['intent'] ?? 'draft') === 'continue' && $step !== null) {
            $index = array_search($step, BusinessOnboardingService::STEPS, true);
            $next = BusinessOnboardingService::STEPS[min($index + 1, count(BusinessOnboardingService::STEPS) - 1)];
        }

        $message = $breakdown['complete']
            ? __('Company profile saved. It is complete, so you can publish PODs.')
            : __('Draft saved. Your company profile is :percentage% complete; :required% is required to publish a POD.', [
                'percentage' => $breakdown['percentage'], 'required' => $breakdown['required'],
            ]);

        return redirect()->route('company.onboarding.edit', array_filter(['step' => $next]))->with('success', $message);
    }
}

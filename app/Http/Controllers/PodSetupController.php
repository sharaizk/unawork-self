<?php

namespace App\Http\Controllers;

use App\Enums\Pod\PodMatchingMode;
use App\Enums\Profile\EngagementType;
use App\Enums\Profile\SkillProficiency;
use App\Enums\Profile\WorkMode;
use App\Http\Requests\Pods\StorePodDraftRequest;
use App\Http\Requests\Pods\UpdatePodSetupRequest;
use App\Models\JobTitle;
use App\Models\Pod;
use App\Models\Skill;
use App\Models\Utility;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Services\Pod\PodConfigurationService;
use App\Services\Pod\PodPublicationRefused;
use App\Services\PodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Guided POD creation (UNA-011): start or resume a draft, configure it step by step, publish.
 * Persistence lives in PodService, scoring in PodConfigurationService.
 */
class PodSetupController extends Controller
{
    public function __construct(
        private PodService $pods,
        private PodConfigurationService $configuration,
        private BusinessOnboardingCompletionService $companies,
    ) {
    }

    /** Start page: never creates anything; lists drafts to resume. */
    public function create(Request $request)
    {
        $user = $request->user();
        abort_if($this->companies->companyWorkspace($user) === null, 403, __('Only a company can create and configure PODs.'));

        return view('pod.setup.start', [
            'drafts' => Pod::drafts()->where('created_by', $user->id)->latest('updated_at')->get(),
            'company' => $this->configuration->companyStatus($user),
            'draftToken' => (string) Str::uuid(),
        ]);
    }

    public function storeDraft(StorePodDraftRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tokenKey = 'pod_draft_tokens.' . preg_replace('/[^A-Za-z0-9-]/', '', (string) ($data['draft_token'] ?? ''));

        // A resubmitted form (refresh/double click) resumes the draft it already created.
        if (!empty($data['draft_token']) && ($existing = Pod::drafts()->where('created_by', $request->user()->id)->find($request->session()->get($tokenKey)))) {
            return redirect()->route('pods.setup.edit', ['pod' => $existing, 'step' => 'basics']);
        }

        $pod = $this->pods->createDraft($request->user(), $data);
        if (!empty($data['draft_token'])) {
            $request->session()->put($tokenKey, $pod->id);
        }

        return redirect()->route('pods.setup.edit', ['pod' => $pod, 'step' => 'basics'])
            ->with('success', __('Draft POD created. Complete the setup, then publish it.'));
    }

    public function edit(Request $request, Pod $pod)
    {
        $this->authorize('configure', $pod);
        $user = $request->user();
        $pod->load(['skills', 'workerRequirements.skills', 'workerRequirements.sourceRequirement']);
        $companyWorkspace = $this->companies->companyWorkspace($user);
        $step = $request->query('step', 'basics');

        return view('pod.setup.edit', [
            'pod' => $pod,
            'breakdown' => $this->configuration->breakdown($pod),
            'company' => $this->configuration->companyStatus($user),
            'canPublish' => $this->configuration->canPublish($pod, $user),
            'step' => in_array($step, UpdatePodSetupRequest::STEPS, true) ? $step : 'basics',
            'steps' => UpdatePodSetupRequest::STEPS,
            'imageUrl' => $pod->image ? Utility::get_file($pod->image) : null,
            'industryId' => $companyWorkspace?->industry_id,
            'templates' => $companyWorkspace ? $companyWorkspace->workerRequirements()->with(['jobTitle', 'skills'])->get() : collect(),
            'jobTitles' => JobTitle::query()->with('industries:id')->alphabetical()->get(['id', 'title']),
            'skills' => Skill::query()->with('industries:id')->alphabetical()->get(['id', 'name']),
            'matchingModes' => PodMatchingMode::cases(),
            'proficiencies' => SkillProficiency::options(),
            'workModes' => WorkMode::options(),
            'engagementTypes' => EngagementType::options(),
        ]);
    }

    public function update(UpdatePodSetupRequest $request, Pod $pod): RedirectResponse
    {
        $data = $request->validated();
        unset($data['image']);
        $step = $data['step'] ?? null;

        try {
            $pod = $this->pods->updateDraft($pod, $request->user(), $data, $request->file('image'));
        } catch (PodPublicationRefused $e) {
            return redirect()->back()->withInput($request->except('image'))->with('error', $e->getMessage());
        }

        $next = $step;
        if (($data['intent'] ?? 'draft') === 'continue' && $step !== null) {
            $index = array_search($step, UpdatePodSetupRequest::STEPS, true);
            $next = UpdatePodSetupRequest::STEPS[min($index + 1, count(UpdatePodSetupRequest::STEPS) - 1)];
        }
        $breakdown = $this->configuration->breakdown($pod);
        $message = $pod->isPublished()
            ? __('POD saved.')
            : __('Draft saved. The POD is :percentage% configured; :required% is required to publish.', ['percentage' => $breakdown['percentage'], 'required' => $breakdown['required']]);

        return redirect()->route('pods.setup.edit', array_filter(['pod' => $pod->id, 'step' => $next]))->with('success', $message);
    }

    public function publish(Request $request, Pod $pod): RedirectResponse|JsonResponse
    {
        // Ownership, configuration and company completeness are re-checked inside PodService::publish().
        $this->authorize('configure', $pod);
        $published = $this->pods->publish($pod, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => __('POD published.'), 'status' => 'published', 'pod_url' => route('landing.pods.show', $published)]);
        }

        return redirect()->route('pods.index')->with('success', __('POD published. It is now visible to workers.'));
    }
}

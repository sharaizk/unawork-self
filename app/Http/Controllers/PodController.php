<?php

namespace App\Http\Controllers;

use App\Models\Pod;
use App\Models\Skill;
use App\Services\PodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PodController extends Controller
{
    protected $podService;

    public function __construct(PodService $podService)
    {
        $this->podService = $podService;
    }

    public function index()
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;
        $pods = $user->pods;
        $businessProfile = $this->businessProfile($user);

        return view('users.pod', compact('currentWorkspace', 'user', 'pods', 'businessProfile'));
    }

    public function create()
    {
        $businessProfile = $this->businessProfile(Auth::user());
        if ($businessProfile && !$businessProfile['complete']) {
            // UNA-010: the create form is replaced by what is missing (PodService still enforces it).
            return view('pod.incomplete_business_profile', compact('businessProfile'));
        }

        $skills = Skill::all();
        return view('pod.create_form', compact('skills'));
    }

    /** Company completion for the POD pages (null for non-companies). UNA-010. */
    private function businessProfile($user): ?array
    {
        $completion = app(\App\Services\Company\BusinessOnboardingCompletionService::class);
        if (!$user || !$user->isCompany()) {
            return null;
        }
        $workspace = $completion->companyWorkspace($user);
        if (!$workspace) {
            return ['percentage' => 0, 'required' => $completion->required(), 'complete' => false, 'missing' => [__('Create your company profile')]];
        }

        return $completion->breakdown($workspace) + ['missing' => $completion->missingRequirements($workspace)];
    }

    public function destroy(int $id)
    {
        $pod = Pod::findOrFail($id);
        $this->authorize('delete', $pod);

        $this->podService->destroyPod($pod);

        return redirect()->route('pods.index');
    }

    public function edit(int $podId)
    {
        $pod = Pod::find($podId);
        $skills = Skill::all();
        return view('pod.edit_form', compact('pod', 'skills'));
    }

    public function list(Request $request)
    {
        $skillIds = $request->input('skill_ids');
        $pods = $this->podService->getPods($skillIds, 15);

        $skills = Skill::all();

        $user = auth()->user();
        $joinedPods = [];

        if ($user) {
            $joinedPods = $user->pods()->pluck('pod_id')->toArray();
        }


        return view('pod.list', compact('pods', 'skills', 'joinedPods', 'user'));
    }

    public function search(Request $request)
    {
        $query = $request->input('name');
        $skillIds = $request->input('skill_ids', []);
        $perPage = 15;

        $podsQuery = Pod::query();

        if (!empty($query)) {
            $podsQuery->where('title', 'LIKE', "%$query%");
        }

        if (!empty($skillIds)) {
            $podsQuery->whereHas('skills', function ($q) use ($skillIds) {
                $q->whereIn('skills.id', $skillIds);
            });
        }

        $pods = $podsQuery->paginate($perPage);

        $user = auth()->user();
        $joinedPods = $user?->pods->pluck('id')->toArray();

        return response()->json([
            'pods' => view('pod.landing.pod_list', compact('pods', 'user', 'joinedPods'))->render(),
            'pagination' => (string) $pods->links('vendor.pagination.custom')
        ]);
    }

    public function show(int $podId)
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;
        $pod = Pod::find($podId);
        return view('pod.manage.index', compact('currentWorkspace', 'pod'));
    }

    public function showOuter(Pod $pod)
    {
        $news = $pod->news()->latest('created_at')->get();
        return view('pod.landing.index', compact('pod', 'news'));
    }

    public function projects(Pod $pod)
    {
        $user = Auth::user();

        $projects = $pod->projects()->get();
        $currentWorkspace = $pod->workspace;

        return view('pod.manage.projects', compact('projects', 'pod', 'currentWorkspace'));
    }

    public function showInner(Pod $pod)
    {
        $this->authorize('viewInner', $pod);

        $objUser = Auth::user();
        $currentWorkspace = $pod->workspace;

        if (!$objUser->can('viewProjects', $currentWorkspace)) {
            // Return custom view if user cannot access this page
            return response()->view('plan_access', [], 403);
        }

        $projects = $pod->projects()->latest('created_at')->get();
        return view('pod.landing.inner', compact('pod', 'projects'));
    }

    public function searchProjects(Request $request, Pod $pod)
    {
        $this->authorize('viewInner', $pod);

        $query = $request->input('query');
        $categoryId = $request->input('category');

        $projects = $pod->projects()
            ->when($query, function ($queryBuilder) use ($query) {
                $queryBuilder->where('projects.name', 'like', "%{$query}%");
            })
            ->when($categoryId, function ($queryBuilder) use ($categoryId) {
                $queryBuilder->where('projects.project_category_id', $categoryId);
            })
            ->latest('created_at')
            ->get();

        return response()->json([
            'projects' => view('pod.landing.inner_projects_list', compact('projects'))->render(),
        ]);
    }

    public function enableInnerCircle($podId)
    {
        $user = Auth::user();
        $pod = Pod::findOrFail($podId);
        $this->authorize('toggleInnerCircle', $pod);

        $plan = $user->getPlan;

        // Checking num of pods with enabled inner_enabled  onto plan
        $enabledPodsCount = $user->countOwnPodsWithInnerEnabled();
        if ($plan->max_inner_circles != -1 && $enabledPodsCount >= $plan->max_inner_circles) {
            return redirect()->back()->with('error', __('You have reached the limit of inner circles allowed by your plan.'));
        }

        $pod->inner_enabled = true;
        $pod->save();

        return redirect()->back()->with('success', __('Inner circle enabled successfully.'));
    }

    public function disableInnerCircle($podId)
    {
        $pod = Pod::findOrFail($podId);
        $this->authorize('toggleInnerCircle', $pod);

        $pod->inner_enabled = false;
        $pod->save();

        return redirect()->back()->with('success', __('Inner Circle has been disabled successfully.'));
    }
}

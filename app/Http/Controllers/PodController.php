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
        return view('users.pod', compact('currentWorkspace', 'user', 'pods'));
    }

    public function create()
    {
        $skills = Skill::all();
        return view('pod.create_form', compact('skills'));
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
        $pods = $this->podService->getPods($skillIds);

        $skills = Skill::all();

        $user = auth()->user();
        $joinedPods = [];

        if ($user) {
            $joinedPods = $user->pods()->pluck('pod_id')->toArray();
        }


        return view('pod.list', compact('pods', 'skills', 'joinedPods', 'user'));
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

    public function enableInnerCircle($podId)
    {
        $user = Auth::user();
        $pod = Pod::findOrFail($podId);

        if ($pod->created_by !== $user->id) {
            return redirect()->back()->with('error', __('You do not have permission to enable inner circle for this pod.'));
        }

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

        if (Auth::id() !== $pod->created_by) {
            return redirect()->back()->with('error', __('You do not have permission to perform this action.'));
        }

        $pod->inner_enabled = false;
        $pod->save();

        return redirect()->back()->with('success', __('Inner Circle has been disabled successfully.'));
    }
}

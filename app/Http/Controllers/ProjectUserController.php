<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\UserProject;
use App\Services\Membership\ProjectMembershipService;
use Illuminate\Support\Facades\Auth;

class ProjectUserController extends Controller
{
    public function __construct(private ProjectMembershipService $projectMemberships)
    {
    }

    public function store(Project $project)
    {
        // Only active members of the project's workspace may submit a claim (UserProjectPolicy::create).
        $this->authorize('create', [UserProject::class, $project]);

        // Eligibility re-checked under lock; claim created atomically (UNA-004); owner notified after commit.
        $result = $this->projectMemberships->submitClaim($project, Auth::user());

        if ($result->applied) {
            return redirect()->back()->with('success', 'You have been successfully sent join request');
        }

        return $result->reason === 'not_workspace_member'
            ? redirect()->back()->with('error', __('You should be in accepted to project workspace first!'))
            : redirect()->back()->with('error', __('You already send Join request!'));
    }

    public function accept(UserProject $record)
    {
        $this->authorize('accept', $record);

        // Only a pending claim can be accepted, exactly once, in one transaction.
        if (!$this->projectMemberships->acceptClaim($record)->applied) {
            return redirect()->back()->with('error', __('This membership request has already been handled.'));
        }

        return redirect()->back()->with('success', __('You have been successfully accepted join request'));
    }

    public function decline(UserProject $record)
    {
        $this->authorize('decline', $record);

        // Only a pending claim can be declined: an accepted member is never downgraded here.
        if (!$this->projectMemberships->declineClaim($record)->applied) {
            return redirect()->back()->with('error', __('This membership request has already been handled.'));
        }

        return redirect()->back()->with('success', __('You have been successfully decline join request'));
    }
}

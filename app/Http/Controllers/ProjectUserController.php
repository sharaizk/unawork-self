<?php

namespace App\Http\Controllers;

use App\Enums\UserWorkspace\Status;
use App\Enums\UserWorkspace\Permission as UserWorkspacePermission;
use App\Models\Notification;
use App\Models\Project;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectUserController extends Controller
{
    public function store(Project $project)
    {
        // Only active members of the project's workspace may submit a claim (UserProjectPolicy::create).
        $this->authorize('create', [UserProject::class, $project]);

        $user = Auth::user();
        $workspace = $project->workspaceData;

        $userInWorkspace = UserWorkspace::where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->where('status', Status::ACTIVE->value)
                ->first();
        if ($userInWorkspace) {
            // Atomic, constraint-backed: an existing claim/membership (any status) is returned
            // untouched, so a repeated or racing request is "already sent", never a 500.
            $userProject = UserProject::findOrCreateMembership($user->id, $project->id, [
                'is_active' => 0,
                'status' => 'pending',
            ]);
            if ($userProject->wasRecentlyCreated) {

                /*
                    if ($userInWorkspace->hasPermissionTo(UserWorkspacePermission::PROJECTS_INDEX->value)) {
                        $userProject = UserProject::create([
                            'project_id' => $project->id,
                            'user_id' => $user->id,
                            'is_active' => 1,
                            'status' => 'active',
                            'permission' => json_encode(Utility::getAllPermission())
                        ]);
                        $responseText = 'You have been successfully joined project';
                        Notification::where('type', 'project_new_for_inner')->where('user_id', $user->id)->whereJsonContains('data->id', $project->id)->delete();
                    } else {
                */
                    Utility::sendNotification('project_request', null, $workspace->created_by, $userProject);
                    $responseText = 'You have been successfully sent join request';
                //  }
                return redirect()->back()->with('success', $responseText);
            } else {
                return redirect()->back()->with('error', __('You already send Join request!'));

            }
        } else {
            return redirect()->back()->with('error', __('You should be in accepted to project workspace first!'));
        }
    }

    public function accept(UserProject $record)
    {
        $this->authorize('accept', $record);

        $user = $record->user;
        $project = $record->project;
        // Only a pending claim can be accepted, exactly once (no duplicate notifications).
        if (!$this->resolvePendingClaim($record, [
            'is_active' => 1,
            'status' => 'active',
            'permission' => json_encode(Utility::getAllPermission()),
        ])) {
            return redirect()->back()->with('error', __('This membership request has already been handled.'));
        }

        $notification = Notification::where('type', 'project_request')
            ->where('user_id', $project->workspaceData->created_by)
            ->whereJsonContains('data->id', $record->id)
            ->first();

        if ($notification) {
            $notification->delete();
        }

        Utility::sendNotification('project_accepted', null, $user->id, $project);


        return redirect()->back()->with('success', __('You have been successfully accepted join request'));
    }

    public function decline(UserProject $record)
    {
        $this->authorize('decline', $record);

        $user = $record->user;
        $project = $record->project;
        // Only a pending claim can be declined: an accepted member is never downgraded here.
        if (!$this->resolvePendingClaim($record, [
            'is_active' => 0,
            'status' => 'declined',
        ])) {
            return redirect()->back()->with('error', __('This membership request has already been handled.'));
        }

        $notification = Notification::where('type', 'project_request')
            ->where('user_id', $project->workspaceData->created_by)
            ->whereJsonContains('data->id', $record->id)
            ->first();

        if ($notification) {
            $notification->delete();
        }

        Utility::sendNotification('project_decline', null, $user->id, $project);


        return redirect()->back()->with('success', __('You have been successfully decline join request'));
    }

    /** Conditional pending -> $attributes transition; false when another request already resolved it. */
    private function resolvePendingClaim(UserProject $record, array $attributes): bool
    {
        $changed = UserProject::whereKey($record->id)->where('status', 'pending')->update($attributes);
        if ($changed === 1) {
            $record->refresh();
        }

        return $changed === 1;
    }
}

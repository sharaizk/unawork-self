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
        $user = Auth::user();
        $workspace = $project->workspaceData;

        $userInWorkspace = UserWorkspace::where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->where('status', Status::ACTIVE->value)
                ->first();
        if ($userInWorkspace) {
            $userInProject = $project->users()->where('user_projects.user_id', $user->id)->exists();
            if (!$userInProject) {

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
                    $userProject = UserProject::create([
                        'project_id' => $project->id,
                        'user_id' => $user->id,
                        'is_active' => 0,
                        'status' => 'pending'
                    ]);
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
        $user = $record->user;
        $project = $record->project;
        $record->update([
            'is_active' => 1,
            'status' => 'active',
            'permission' => json_encode(Utility::getAllPermission())
        ]);

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
        $user = $record->user;
        $project = $record->project;
        $record->update([
            'is_active' => 0,
            'status' => 'declined'
        ]);

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
}

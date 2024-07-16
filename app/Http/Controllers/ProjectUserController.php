<?php

namespace App\Http\Controllers;

use App\Enums\UserWorkspace\Status;
use App\Models\Notification;
use App\Models\Project;
use App\Models\UserProject;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectUserController extends Controller
{
    public function store(Project $project)
    {
        $user = Auth::user();
        $workspace = $project->workspaceData;

        $userInWorkspace = $workspace->users()->wherePivot('status', Status::ACTIVE->value)->where('user_id', $user->id)->exists();

        if ($userInWorkspace) {
            $userInProject = $project->users()->where('user_projects.user_id', $user->id)->exists();
            if (!$userInProject) {
                $userProject = UserProject::create([
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'is_active' => 0,
                    'status' => 'pending'
                ]);    

                Utility::sendNotification('project_request', null, $workspace->created_by, $userProject);
                return redirect()->back()->with('success', __('You have been successfully sended join request'));
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
            $notification->update(['is_read' => 1]);
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
            $notification->update(['is_read' => 1]);
        }

        Utility::sendNotification('project_decline', null, $user->id, $project);


        return redirect()->back()->with('success', __('You have been successfully decline join request'));
    }
}

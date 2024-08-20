<?php

namespace App\Observers;

use App\Models\Workspace;
use App\Models\User;
use App\Models\ZoomMeeting;

class WorkspaceObserver
{
    public function deleting(Workspace $workspace)
    {
        // delete all Zoom meetings, for this workspace
        ZoomMeeting::where('workspace_id', $workspace->id)->delete();
    }

    /**
     * Handle the Workspace "deleted" event.
     */
    public function deleted(Workspace $workspace): void
    {
        $users = User::where('currant_workspace', $workspace->id)->get();

        foreach ($users as $user) {
            $currantWorkspace = Workspace::where('created_by', $user->id)->first();
            if ($currantWorkspace) {
                $user->currant_workspace = $currantWorkspace->id;
                $user->update();
            } else {
                $user->is_enable_login = 0;
                $user->is_active = 0;
                $user->update();
            }
        }
    }

    /**
     * Handle the Workspace "created" event.
     */
    public function created(Workspace $workspace): void
    {
        //
    }

    /**
     * Handle the Workspace "updated" event.
     */
    public function updated(Workspace $workspace): void
    {
        //
    }

    

    /**
     * Handle the Workspace "restored" event.
     */
    public function restored(Workspace $workspace): void
    {
        //
    }

    /**
     * Handle the Workspace "force deleted" event.
     */
    public function forceDeleted(Workspace $workspace): void
    {
        //
    }
}

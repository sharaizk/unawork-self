<?php

namespace App\Observers;

use App\Models\Workspace;
use App\Models\ZoomMeeting;

class WorkspaceObserver
{
    public function deleting(Workspace $workspace)
    {
        // delete all Zoom meetings, for this workspace
        ZoomMeeting::where('workspace_id', $workspace->id)->delete();
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
     * Handle the Workspace "deleted" event.
     */
    public function deleted(Workspace $workspace): void
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

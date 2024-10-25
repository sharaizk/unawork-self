<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Plan;

class ProjectPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewProjects(User $user, Workspace $workspace)
    {
        $creator = $workspace->created_by;

        // Get creator plan
        $plan = Plan::find($creator->plan_id);

        if (!$plan) {
            return false;
        }

        if ($workspace->isPartOfPod()) {
            return $plan->allow_pod_project_view;
        } else {
            return $plan->allow_workspace_project_view;
        }
    }
}

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
        $creator = $workspace->creater;
        if (!$creator) {
            return false;
        }

        // Get creator plan
        $plan = Plan::find($creator->plan);

        if (!$plan) {
            return false;
        }

        if ($workspace->isPartOfPod()) {
            $pod = $workspace->ownerable;
            return $plan->allow_pod_project_view  && $pod->inner_enabled;
        } else {
            return $plan->allow_workspace_project_view;
        }
    }

    public function viewGanntChartManagement(User $user, Workspace $workspace)
    {
        $creator = $workspace->creater;
        if (!$creator) {
            return false;
        }
        // Get creator plan
        $plan = Plan::find($creator->plan);
        if (!$plan) {
            return false;
        }
        return $plan->allow_gannt_chart_project_management;
    }

    public function viewBugReportManagement(User $user, Workspace $workspace)
    {
        $creator = $workspace->creater;
        if (!$creator) {
            return false;
        }
        // Get creator plan
        $plan = Plan::find($creator->plan);
        if (!$plan) {
            return false;
        }
        return $plan->allow_bug_report_project_management;
    }

    public function viewExpesesManagement(User $user, Workspace $workspace)
    {
        $creator = $workspace->creater;
        if (!$creator) {
            return false;
        }
        // Get creator plan
        $plan = Plan::find($creator->plan);
        if (!$plan) {
            return false;
        }
        return $plan->allow_expenses_project_management;
    }

    public function viewFilesManagement(User $user, Workspace $workspace)
    {
        $creator = $workspace->creater;
        if (!$creator) {
            return false;
        }
        // Get creator plan
        $plan = Plan::find($creator->plan);
        if (!$plan) {
            return false;
        }
        return $plan->allow_files_project_management;
    }

    public function viewChatManagement(User $user, Workspace $workspace)
    {
        $creator = $workspace->creater;
        if (!$creator) {
            return false;
        }
        // Get creator plan
        $plan = Plan::find($creator->plan);
        if (!$plan) {
            return false;
        }
        return $plan->allow_chat_project_management;
    }
}

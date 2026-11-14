<?php

namespace App\Policies;

use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Workspace;

/**
 * Project join/claim requests (user_projects rows created by ProjectUserController).
 *
 * Claimant  = an active member of the project's workspace (POD or company workspace).
 * Moderator = the workspace owner ($workspace->created_by, also the POD owner for POD
 *             workspaces) or an active workspace member with projects_edit.
 * No global admin bypass: super admins follow the same rules.
 */
class UserProjectPolicy
{
    /**
     * Submit a join request for $project as oneself.
     * Usage: $this->authorize('create', [UserProject::class, $project]).
     */
    public function create(User $user, Project $project): bool
    {
        $workspace = $project->workspaceData;

        return $workspace !== null && $this->activeMembership($user, $workspace) !== null;
    }

    public function accept(User $user, UserProject $record): bool
    {
        return $this->canModerate($user, $record);
    }

    public function decline(User $user, UserProject $record): bool
    {
        return $this->canModerate($user, $record);
    }

    private function canModerate(User $user, UserProject $record): bool
    {
        // A claimant can never approve or reject their own request.
        if ((int) $record->user_id === (int) $user->id) {
            return false;
        }

        $workspace = $record->project?->workspaceData;
        if (!$workspace) {
            return false;
        }

        if ((int) $workspace->created_by === (int) $user->id) {
            return true;
        }

        $membership = $this->activeMembership($user, $workspace);

        return $membership !== null
            && $membership->checkPermissionTo(WorkspacePermission::PROJECTS_EDIT->value);
    }

    private function activeMembership(User $user, Workspace $workspace): ?UserWorkspace
    {
        return UserWorkspace::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('status', WorkspaceStatus::ACTIVE->value)
            ->first();
    }
}

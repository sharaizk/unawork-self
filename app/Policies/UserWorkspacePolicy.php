<?php

namespace App\Policies;

use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;

/**
 * Company-workspace join requests (user_workspaces rows created by UserController::joinRequest).
 *
 * Moderator = the workspace owner ($workspace->created_by, who receives the workspace_join
 * notification) or an active member of that workspace with users_edit, the same "manager" rule
 * PodPolicy uses. The requester never decides their own request. POD workspaces are moderated
 * through PodPolicy / PodUserService instead. No global admin bypass.
 */
class UserWorkspacePolicy
{
    public function acceptJoinRequest(User $user, UserWorkspace $request): bool
    {
        return $this->canModerate($user, $request);
    }

    public function declineJoinRequest(User $user, UserWorkspace $request): bool
    {
        return $this->canModerate($user, $request);
    }

    private function canModerate(User $user, UserWorkspace $request): bool
    {
        if ((int) $request->user_id === (int) $user->id) {
            return false;
        }

        $workspace = Workspace::find($request->workspace_id);
        if (!$workspace || $workspace->isPartOfPod()) {
            return false;
        }

        if ((int) $workspace->created_by === (int) $user->id) {
            return true;
        }

        $membership = UserWorkspace::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('status', WorkspaceStatus::ACTIVE->value)
            ->first();

        return $membership !== null
            && $membership->checkPermissionTo(WorkspacePermission::USERS_EDIT->value);
    }
}

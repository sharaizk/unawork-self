<?php

namespace App\Policies;

use App\Enums\PodUser\Status as PodUserStatus;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Auth\Access\Response;

class PodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    // public function viewAny(User $user): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can view the model.
     */
    // public function view(User $user, Pod $pod): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can create models.
     */
    // public function create(User $user): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Pod $pod): bool
    {
        return $user->id === $pod->created_by;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Pod $pod): bool
    {
        return $user->id === $pod->created_by;
    }

    /**
     * Determine whether the user can restore the model.
     */
    // public function restore(User $user, Pod $pod): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can permanently delete the model.
     */
    // public function forceDelete(User $user, Pod $pod): bool
    // {
    //     //
    // }

    public function viewInner(User $user, Pod $pod): bool
    {
        $workspace = $pod->workspace;
        $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->first();

        return $userWorkspace && $userWorkspace->hasPermissionTo('projects_index');
    }

    /*
    |--------------------------------------------------------------------------
    | Membership management (UNA-001)
    |--------------------------------------------------------------------------
    |
    | Owner   = $pod->created_by (also holds the "Owner" pod_users row).
    | Manager = active member of the POD workspace with users_edit.
    | No global admin bypass: super admins follow the same rules.
    */

    /**
     * View the POD member-management page (pods.users).
     * Any non-declined member of the POD workspace, including pending invitees
     * who need the page to answer their own invitation.
     */
    public function viewMembers(User $user, Pod $pod): bool
    {
        if ($this->isOwner($user, $pod)) {
            return true;
        }

        $userWorkspace = $this->workspaceMembership($user, $pod);

        return $userWorkspace !== null && $userWorkspace->status !== WorkspaceStatus::DECLINED->value;
    }

    /**
     * Invite users, open the invite form and list join requests.
     */
    public function manageMembers(User $user, Pod $pod): bool
    {
        return $this->hasWorkspacePermission($user, $pod, WorkspacePermission::USERS_EDIT);
    }

    /**
     * Accept a pending membership for $member.
     *  - pod_pending (member asked to join): a manager decides.
     *  - employee_pending (POD invited member): only the invited user decides.
     */
    public function acceptMember(User $user, Pod $pod, User $member): bool
    {
        return $this->canRespondToMembership($user, $pod, $member);
    }

    public function declineMember(User $user, Pod $pod, User $member): bool
    {
        return $this->canRespondToMembership($user, $pod, $member);
    }

    /**
     * Remove $member from the POD. Members may remove themselves (leave);
     * managers may remove others. Nobody can remove or "leave" as the owner.
     */
    public function removeMember(User $user, Pod $pod, User $member): bool
    {
        if ($this->isOwner($member, $pod) || $this->podMembership($pod, $member) === null) {
            return false;
        }

        if ((int) $user->id === (int) $member->id) {
            return true;
        }

        return $this->manageMembers($user, $pod);
    }

    /**
     * View/update $member's workspace permissions, including Inner Circle
     * promotion/demotion (projects_index). The owner's permissions are fixed.
     */
    public function updateMemberPermissions(User $user, Pod $pod, User $member): bool
    {
        if ($this->isOwner($member, $pod) || $this->podMembership($pod, $member) === null) {
            return false;
        }

        return $this->manageMembers($user, $pod);
    }

    /**
     * Enable/disable the Inner Circle. Owner only: the quota is taken from the
     * owner's plan.
     */
    public function toggleInnerCircle(User $user, Pod $pod): bool
    {
        return $this->isOwner($user, $pod);
    }

    /**
     * Submit a request to join the POD as oneself. Duplicate requests are a
     * business rule handled by PodUserService.
     */
    public function requestToJoin(User $user, Pod $pod): bool
    {
        return !$this->isOwner($user, $pod) && $pod->workspace !== null;
    }

    /**
     * Moderate a join request (API). The request must belong to this POD.
     */
    public function moderateJoinRequest(User $user, Pod $pod, PodUser $joinRequest): bool
    {
        return (int) $joinRequest->pod_id === (int) $pod->id && $this->manageMembers($user, $pod);
    }

    private function canRespondToMembership(User $user, Pod $pod, User $member): bool
    {
        $membership = $this->podMembership($pod, $member);
        if ($membership === null) {
            return false;
        }

        if ($membership->status === PodUserStatus::POD_PENDING->value) {
            return (int) $user->id !== (int) $member->id && $this->manageMembers($user, $pod);
        }

        if ($membership->status === PodUserStatus::EMPLOYEE_PENDING->value) {
            return (int) $user->id === (int) $member->id;
        }

        return false;
    }

    private function isOwner(User $user, Pod $pod): bool
    {
        return (int) $user->id === (int) $pod->created_by;
    }

    private function podMembership(Pod $pod, User $member): ?PodUser
    {
        return PodUser::where('pod_id', $pod->id)->where('user_id', $member->id)->first();
    }

    private function workspaceMembership(User $user, Pod $pod): ?UserWorkspace
    {
        $workspace = $pod->workspace;
        if (!$workspace) {
            return null;
        }

        return UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->first();
    }

    private function hasWorkspacePermission(User $user, Pod $pod, WorkspacePermission $permission): bool
    {
        if ($this->isOwner($user, $pod)) {
            return true;
        }

        $userWorkspace = $this->workspaceMembership($user, $pod);

        // checkPermissionTo() returns false (instead of throwing) for unknown permissions.
        return $userWorkspace !== null
            && $userWorkspace->status === WorkspaceStatus::ACTIVE->value
            && $userWorkspace->checkPermissionTo($permission->value);
    }
}

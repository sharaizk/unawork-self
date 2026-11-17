<?php

namespace App\Policies;

use App\Enums\PodUser\State;
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
     * UNA-011: configure (edit setup of) a draft or published POD, and publish a draft - only the
     * company account that owns it. Never derived from the current workspace.
     */
    public function configure(User $user, Pod $pod): bool
    {
        return app(\App\Services\Pod\PodConfigurationService::class)->isOwner($pod, $user);
    }

    public function publish(User $user, Pod $pod): bool
    {
        return $pod->isDraft() && $this->configure($user, $pod);
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

    /**
     * Inner Circle pages: the explicit inner_circle state (workflow) AND project visibility
     * (access). Neither alone is enough (UNA-006).
     */
    public function viewInner(User $user, Pod $pod): bool
    {
        $membership = $this->podMembership($pod, $user);
        $userWorkspace = $this->workspaceMembership($user, $pod);

        return $membership !== null
            && $membership->state === State::INNER_CIRCLE
            && $userWorkspace !== null
            && $userWorkspace->checkPermissionTo(WorkspacePermission::PROJECTS_INDEX->value);
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

        // Applicants (to answer their own invitation) and active members; never rejected,
        // suspended or removed members.
        $membership = $this->podMembership($pod, $user);

        return $membership !== null
            && in_array($membership->state, [...State::applicants(), ...State::activeMembers()], true)
            && $this->workspaceMembership($user, $pod) !== null;
    }

    /**
     * Invite users, open the invite form and list join requests.
     */
    public function manageMembers(User $user, Pod $pod): bool
    {
        return $this->hasWorkspacePermission($user, $pod, WorkspacePermission::USERS_EDIT);
    }

    /**
     * Apply a validated state transition (screening, suspension, resumption, circle moves) to
     * $member. Managers only; never the owner or oneself. The graph is enforced by
     * PodStateTransitionService.
     */
    public function changeMemberState(User $user, Pod $pod, User $member): bool
    {
        if ($this->isOwner($member, $pod) || (int) $user->id === (int) $member->id) {
            return false;
        }
        $membership = $this->podMembership($pod, $member);

        return $membership !== null && $membership->state !== State::REMOVED && $this->manageMembers($user, $pod);
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
        $membership = $this->podMembership($pod, $member);
        if ($this->isOwner($member, $pod) || $membership === null || $membership->state === State::REMOVED) {
            return false;
        }

        if ((int) $user->id === (int) $member->id) {
            return true;
        }

        return $this->manageMembers($user, $pod);
    }

    /**
     * View/update $member's workspace permissions and their circle (an explicit circle_state
     * target handled by PodStateTransitionService). The owner's membership is fixed.
     */
    public function updateMemberPermissions(User $user, Pod $pod, User $member): bool
    {
        $membership = $this->podMembership($pod, $member);
        if ($this->isOwner($member, $pod) || $membership === null || $membership->state === State::REMOVED) {
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
        // UNA-011: only published PODs accept applications. UNA-017: only employees apply.
        return $user->isEmployee() && $pod->isPublished() && !$this->isOwner($user, $pod) && $pod->workspace !== null;
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

        // An application is decided by a manager; an invitation only by the invited user.
        if ($membership->state === State::PENDING_BUSINESS_APPROVAL) {
            return (int) $user->id !== (int) $member->id && $this->manageMembers($user, $pod);
        }

        if ($membership->state === State::MATCHED) {
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
        // Workflow: an active POD member. Access: an active workspace membership with the permission.
        $membership = $this->podMembership($pod, $user);

        return $membership !== null
            && in_array($membership->state, State::activeMembers(), true)
            && $userWorkspace !== null
            && $userWorkspace->status === WorkspaceStatus::ACTIVE->value
            && $userWorkspace->checkPermissionTo($permission->value);
    }
}

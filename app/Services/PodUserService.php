<?php

namespace App\Services;

use App\Enums\PodUser\Permission;
use App\Enums\PodUser\Status;
use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Permission as UserWorkspacePermission;
use App\Enums\UserWorkspace\Status as UserWorkspaceStatus;
use App\Jobs\SendEmail;
use App\Models\Notification;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use App\Services\Membership\AfterCommit;
use App\Services\Membership\CurrentWorkspace;
use App\Services\Membership\MembershipResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * POD membership workflows (UNA-005). Every operation that touches several membership records
 * runs in ONE DB::transaction() (retried on deadlock) and returns a MembershipResult; external
 * effects (notifications, emails) run only after commit via AfterCommit. Authorization happens
 * in the controllers (UNA-001 policies) before these methods are called; state is re-checked
 * here under row locks.
 */
class PodUserService
{
    /** Deadlock retries; effects are built per attempt and run once, after the commit. */
    public const ATTEMPTS = 3;

    /** Permissions every POD member's workspace membership keeps (the "outer circle"). */
    public const OUTER_CIRCLE = [UserWorkspacePermission::USERS_INDEX, UserWorkspacePermission::NEWS_INDEX];

    /**
     * Inner Circle marker: projects_index lets a member see every project of the POD workspace
     * (PodPolicy::viewInner, sidebar, ProjectController::index). Without it a member still sees
     * the projects they belong to through user_projects (claims/invitations moderated separately),
     * so demotion does NOT remove project memberships or task assignments.
     */
    public const INNER_CIRCLE = UserWorkspacePermission::PROJECTS_INDEX;

    /** Capabilities that make no sense without project visibility; revoked on demotion. */
    public const REQUIRES_INNER_CIRCLE = [UserWorkspacePermission::PROJECTS_EDIT];

    public function __construct(
        private ?AfterCommit $afterCommit = null,
        private ?CurrentWorkspace $currentWorkspace = null,
    ) {
        $this->afterCommit ??= new AfterCommit();
        $this->currentWorkspace ??= new CurrentWorkspace();
    }

    // ------------------------------------------------------------------ invitations / requests

    public function inviteExistedUser(int $userId = null, int $podId, array $settings): MembershipResult
    {
        $user = User::find($userId);
        $pod = Pod::find($podId);

        if (is_null($user)) {
            throw new \Exception(__('This User is not connected with our system. Please fill out the below details to invite.'));
        }

        if ($this->isUserAssignedToPod($user, $podId)) {
            throw new \Exception(__('This user is already a member of this pod.'));
        }

        $result = $this->run(function () use ($user, $pod, $settings) {
            $podUser = $this->attachUserToPod($user, $pod->id);
            if (!$podUser->wasRecentlyCreated) {
                return MembershipResult::unchanged($podUser, 'already_member');
            }
            $this->attachUserToPodWorkspaces($user, $pod);

            return MembershipResult::applied($podUser, $podUser->status, [
                'notify_invitee' => fn () => Utility::sendNotification('pod_invite', null, $user->id, $pod),
                'invitation_email' => fn () => $this->sendInvitationEmail($user, $pod, $settings),
            ]);
        });

        if (!$result->applied) {
            throw new \Exception(__('This user is already a member of this pod.'));
        }

        return $result;
    }

    /** New employee account + POD membership + POD workspace membership, together or not at all. */
    public function inviteNewUser(array $data, int $podId, array $settings): MembershipResult
    {
        $pod = Pod::findOrFail($podId);

        return $this->run(function () use ($data, $pod, $settings) {
            $user = $this->createNewUser($data);
            $podUser = $this->attachUserToPod($user, $pod->id);
            $this->attachUserToPodWorkspaces($user, $pod);

            return MembershipResult::applied($podUser, $podUser->status, [
                'invitation_email' => fn () => $this->sendInvitationEmail($user, $pod, $settings),
                'registration_email' => fn () => $this->sendRegistrationEmail($user, $settings),
            ]);
        });
    }

    public function requestJoinPod(int $userId, int $podId): MembershipResult
    {
        $pod = Pod::findOrFail($podId);
        $user = User::findOrFail($userId);

        // An existing membership (any status) is returned untouched, never overwritten (UNA-004).
        $result = $this->run(function () use ($user, $pod) {
            $podUser = PodUser::findOrCreateMembership($user->id, $pod->id, [
                'permission' => Permission::MEMBER->value,
                'status' => Status::POD_PENDING->value,
            ]);
            if (!$podUser->wasRecentlyCreated) {
                return MembershipResult::unchanged($podUser, 'already_requested');
            }
            $this->attachUserToPodWorkspaces($user, $pod);

            return MembershipResult::applied($podUser, $podUser->status, [
                'notify_owner' => fn () => Utility::sendNotification('pod_request', null, $pod->created_by, $podUser),
            ]);
        });

        if (!$result->applied) {
            throw new \Exception('You have already submitted a join request for this pod.');
        }

        return $result;
    }

    public function getAllJoinRequests(int $podId)
    {
        return PodUser::where('pod_id', $podId)->where('status', Status::POD_PENDING->value)->get();
    }

    // ------------------------------------------------------------------ accept / decline

    /**
     * Resolve a pending request (pod_pending) or invitation (employee_pending) exactly once.
     * POD and workspace membership change together; the actionable notifications are removed in
     * the same transaction. A second or racing call returns unchanged('already_handled').
     *
     * @param Status[]|null $allowedFrom restrict to one direction (API moderation: requests only)
     */
    public function respondToMembership(Pod $pod, User $member, Status $to, ?array $allowedFrom = null, ?string $reason = null): MembershipResult
    {
        $allowedFrom ??= [Status::POD_PENDING, Status::EMPLOYEE_PENDING];

        return $this->run(function () use ($pod, $member, $to, $allowedFrom, $reason) {
            $podUser = PodUser::where('pod_id', $pod->id)->where('user_id', $member->id)->lockForUpdate()->first();
            $from = $podUser ? Status::tryFrom((string) $podUser->status) : null;
            if (!$from || !in_array($from, $allowedFrom, true)) {
                return MembershipResult::unchanged($podUser, 'already_handled');
            }

            $podUpdate = ['status' => $to->value] + ($to === Status::DECLINED ? ['reason' => $reason] : []);
            $changed = PodUser::whereKey($podUser->id)->where('status', $from->value)->update($podUpdate);
            if ($changed !== 1) {
                return MembershipResult::unchanged($podUser, 'already_handled');
            }
            $podUser->refresh();

            $workspace = $pod->workspace;
            $userWorkspace = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $member->id)->lockForUpdate()->first();

            if ($to === Status::ACTIVE) {
                // Legacy invitations may lack the workspace row: create it so access matches.
                $userWorkspace ??= UserWorkspace::findOrCreateMembership($member->id, $workspace->id, ['permission' => 'Member']);
                $userWorkspace->forceFill(['permission' => $userWorkspace->permission === 'Owner' ? 'Owner' : 'Member', 'is_active' => 1, 'status' => UserWorkspaceStatus::ACTIVE->value])->save();
                $userWorkspace->givePermissionTo(array_map(fn ($p) => $p->value, self::OUTER_CIRCLE));
            } elseif ($userWorkspace) {
                $userWorkspace->forceFill(['is_active' => 0, 'status' => UserWorkspaceStatus::DECLINED->value])->save();
                $userWorkspace->syncPermissions([]); // a declined membership keeps no access
                $this->currentWorkspace->reassignIfLost($member, $workspace->id);
            }

            $this->deleteActionNotifications($pod, $podUser, $userWorkspace);

            $suffix = $to === Status::ACTIVE ? 'accept' : 'decline';

            return MembershipResult::applied($podUser, $podUser->status, [
                'notify_owner' => fn () => Utility::sendNotification("pod_request_{$suffix}_owner", null, $pod->created_by, $podUser),
                'notify_member' => fn () => Utility::sendNotification("pod_request_{$suffix}_user", null, $member->id, $podUser),
            ]);
        });
    }

    /** API moderation of a join request (pod_pending only); a reason is required to decline. */
    public function moderateJoinRequest(PodUser $request, string $action, ?string $reason = null): MembershipResult
    {
        if ($action === 'decline' && is_null($reason)) {
            throw new \Exception('Reason is required for declining a request');
        }
        if (!in_array($action, ['accept', 'decline'], true)) {
            throw new \Exception('Invalid action');
        }

        return $this->respondToMembership(
            $request->pod,
            $request->user,
            $action === 'accept' ? Status::ACTIVE : Status::DECLINED,
            [Status::POD_PENDING],
            $reason
        );
    }

    // ------------------------------------------------------------------ Inner Circle

    /**
     * Replace $user's workspace permissions in $pod (promotion/demotion happen here).
     * Under locks: outer-circle permissions are kept for active POD members; projects_edit is
     * dropped without projects_index; only active POD members can be promoted. The Inner Circle
     * notification is sent after commit.
     */
    public function updatePermissions(Pod $pod, User $user, array $permissions): MembershipResult
    {
        $known = UserWorkspacePermission::values();
        $unknown = array_diff($permissions, $known);
        if ($unknown) {
            throw new \InvalidArgumentException(__('Invalid permission selected.'));
        }

        $result = $this->run(function () use ($pod, $user, $permissions) {
            $workspace = $pod->workspace;
            $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->lockForUpdate()->first();
            if (!$userWorkspace) {
                throw new \Exception(__('This user is not a member of this pod workspace.'));
            }
            $podUser = PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->lockForUpdate()->first();
            $activeMember = $podUser && $podUser->status === Status::ACTIVE->value;

            $target = array_values(array_unique($permissions));
            if (!in_array(self::INNER_CIRCLE->value, $target, true)) {
                $target = array_values(array_diff($target, array_map(fn ($p) => $p->value, self::REQUIRES_INNER_CIRCLE)));
            }
            if ($activeMember) {
                $target = array_values(array_unique(array_merge($target, array_map(fn ($p) => $p->value, self::OUTER_CIRCLE))));
            }

            $old = $userWorkspace->getPermissionNames()->all();
            $wasInner = in_array(self::INNER_CIRCLE->value, $old, true);
            $isInner = in_array(self::INNER_CIRCLE->value, $target, true);
            if (!$wasInner && $isInner && !$activeMember) {
                return MembershipResult::unchanged($userWorkspace, 'not_active_member');
            }

            $userWorkspace->syncPermissions($target);

            return MembershipResult::applied($userWorkspace, $userWorkspace->status, [
                'notify_inner_circle' => !$wasInner && $isInner
                    ? fn () => Utility::sendNotification('pod_inner_circle_invite', null, $user->id, $pod)
                    : null,
            ]);
        });

        if (!$result->applied) {
            throw new \Exception(__('Only active POD members can join the Inner Circle.'));
        }

        return $result;
    }

    // ------------------------------------------------------------------ removal / leave

    /**
     * Remove $userId from the POD (manager removal or voluntary leave) with every dependent
     * record, in one transaction: task assignments and project memberships in the POD
     * workspace, the workspace membership (and, through model deletion, its Spatie permissions
     * and roles), the POD membership, actionable notifications and the current workspace.
     */
    public function remove(int $userId, int $podId): MembershipResult
    {
        $pod = Pod::findOrFail($podId);
        $user = User::findOrFail($userId);

        return $this->run(function () use ($pod, $user) {
            $podUser = PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$podUser) {
                return MembershipResult::unchanged(null, 'not_member');
            }
            if ($podUser->permission === Permission::OWNER->value) {
                throw new \DomainException('The POD owner cannot be removed.'); // also denied by PodPolicy
            }

            $workspace = $pod->workspace;
            $userWorkspace = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $user->id)->lockForUpdate()->first();

            $claimIds = $this->detachUserFromWorkspaceProjects($workspace, $user->id);
            $userWorkspace?->delete(); // Spatie's deleting hooks remove model_has_permissions/roles
            $this->deleteActionNotifications($pod, $podUser, $userWorkspace, $claimIds);
            $podUser->delete();
            $this->currentWorkspace->reassignIfLost($user, $workspace->id);

            return MembershipResult::applied(null, null, [
                'notify_removed' => fn () => Utility::sendNotification('pod_remove', null, $user->id, $pod),
            ]);
        });
    }

    public function getBackUrl($ownId, $userId, $podId): string
    {
        if ($ownId != $userId) {
            return route('pods.users', ['podId' => $podId]);
        }

        // The leaving user's current workspace was reassigned in the removal transaction.
        $current = User::find($userId)?->currant_workspace;

        return $current ? route('change-workspace', ['id' => $current]) : route('home');
    }

    public function list(Pod $pod, int $ownId, $workspaceId): Collection
    {
        $userWorkspace = UserWorkspace::where('user_id', $ownId)->where('workspace_id', $workspaceId)->firstOrFail();

        if ($userWorkspace->hasPermissionTo(UserWorkspacePermission::USERS_INDEX->value) || $pod->created_by == $ownId) {
            $users = $pod->users;
        } else {
            $users = $pod->users->where('id', $ownId);
        }

        return $users;
    }

    // ------------------------------------------------------------------ internals

    /** One transaction (retried on deadlock), then the committed attempt's effects. */
    private function run(\Closure $operation): MembershipResult
    {
        return $this->afterCommit->run(DB::transaction($operation, self::ATTEMPTS));
    }

    /** @return int[] ids of the project memberships/claims removed */
    private function detachUserFromWorkspaceProjects(Workspace $workspace, int $userId): array
    {
        $projectIds = Project::where('workspace', $workspace->id)->pluck('id');

        // Exact element match on the comma-separated assign_to list (same as FIND_IN_SET).
        Task::whereIn('project_id', $projectIds)->whereNotNull('assign_to')->lockForUpdate()->get()
            ->each(function (Task $task) use ($userId) {
                $assignees = array_filter(explode(',', (string) $task->assign_to), fn ($id) => $id !== '');
                $remaining = array_values(array_filter($assignees, fn ($id) => (int) $id !== $userId));
                if (count($remaining) !== count($assignees)) {
                    $task->assign_to = implode(',', $remaining);
                    $task->save();
                }
            });

        $claimIds = UserProject::whereIn('project_id', $projectIds)->where('user_id', $userId)->pluck('id')->all();
        UserProject::whereIn('id', $claimIds)->delete();

        return $claimIds;
    }

    /** Actionable notifications whose buttons no longer apply once the membership changed. */
    private function deleteActionNotifications(Pod $pod, PodUser $podUser, ?UserWorkspace $userWorkspace, array $claimIds = []): void
    {
        Notification::where('type', 'pod_request')->where('related_id', $podUser->id)->delete();
        Notification::where('type', 'pod_invite')->where('user_id', $podUser->user_id)->where('related_id', $pod->id)->delete();
        if ($userWorkspace) {
            Notification::whereIn('type', ['workspace_invite', 'workspace_join'])->where('related_id', $userWorkspace->id)->delete();
        }
        if ($claimIds) {
            Notification::where('type', 'project_request')->whereIn('related_id', $claimIds)->delete();
        }
    }

    private function createNewUser(array $data): User
    {
        // Accounts generated by a POD invitation are employees.
        return User::createWithAccountRole([
            'name' => $data['name'],
            'email' => $data['email'],
            'plan' => NULL,
            'password' => Hash::make($data['password']), // should use bcrypt for hashing password
            'email_verified_at' => date('Y-m-d i:h:s'),
            'is_enable_login' => 1,
        ], UserRole::EMPLOYEE);
    }

    private function isUserAssignedToPod(User $user, int $podId): bool
    {
        return $user->pods()
            ->where('pods.id', $podId)
            ->exists();
    }

    /** Invitation membership; an existing membership is returned unchanged (check wasRecentlyCreated). */
    private function attachUserToPod(User $user, int $podId): PodUser
    {
        return PodUser::findOrCreateMembership($user->id, $podId, [
            'permission' => Permission::MEMBER->value,
            'status' => Status::EMPLOYEE_PENDING->value,
        ]);
    }

    /** POD workspace membership with the outer-circle defaults. Call inside a transaction. */
    private function attachUserToPodWorkspaces(User $user, Pod $pod): void
    {
        $workspace = $pod->workspace ?? throw new \DomainException("POD {$pod->id} has no workspace.");

        // Existing workspace memberships keep their status; only a new one starts pending.
        $userWorkspace = UserWorkspace::findOrCreateMembership($user->id, $workspace->id, [
            'permission' => 'Member',
            'is_active' => 1,
            'status' => UserWorkspaceStatus::EMPLOYEE_PENDING->value,
        ]);
        $userWorkspace->givePermissionTo(array_map(fn ($p) => $p->value, self::OUTER_CIRCLE));
    }

    private function sendInvitationEmail(User $user, Pod $pod, array $settings): void
    {
        $message = "You have been invited to join the pod: ".$pod->title." !";
        $subject = 'Pod Invitation';
        SendEmail::dispatch($user->email, $message, $subject, $settings);
    }

    private function sendRegistrationEmail(User $user, array $settings): void
    {
        $message = "You have been succesfully registered!";
        $subject = 'Pod Invitation';
        SendEmail::dispatch($user->email, $message, $subject, $settings);
    }
}

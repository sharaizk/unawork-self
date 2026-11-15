<?php

namespace App\Services\Membership;

use App\Enums\UserWorkspace\Status;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use App\Services\Provisioning\ProvisionUserData;
use App\Services\Provisioning\UserProvisioningService;
use Illuminate\Support\Facades\DB;

/**
 * Company-workspace join requests (workspace_pending) and invitations (employee_pending), each
 * resolved exactly once inside one transaction (UNA-005). POD workspaces are resolved through
 * PodUserService instead, so POD and workspace membership can never disagree.
 */
class WorkspaceMembershipService
{
    public const ATTEMPTS = 3;

    public function __construct(
        private ?AfterCommit $afterCommit = null,
        private ?CurrentWorkspace $currentWorkspace = null,
        private ?UserProvisioningService $provisioning = null,
    ) {
        $this->afterCommit ??= new AfterCommit();
        $this->currentWorkspace ??= new CurrentWorkspace();
        $this->provisioning ??= app(UserProvisioningService::class);
    }

    /**
     * Invite an email to a company workspace (UNA-012). One transaction provisions the invitee
     * (a new employee, or a compatible existing employee whose account structure is repaired) and
     * creates the pending invitation; companies and administrators are refused (IncompatibleInvitee).
     * The invitee's current workspace stays their own workspace until they accept. A repeated
     * invitation returns 'already_member' and sends nothing.
     *
     * @throws \App\Services\Provisioning\ProvisioningException
     */
    public function invite(Workspace $workspace, User $inviter, ProvisionUserData $data): MembershipResult
    {
        $result = DB::transaction(function () use ($workspace, $inviter, $data) {
            if ($workspace->isPartOfPod()) {
                return MembershipResult::unchanged(null, 'pod_workspace');
            }
            $provisioned = $this->provisioning->apply($data);
            $user = $provisioned->user;

            $membership = UserWorkspace::findOrCreateMembership($user->id, $workspace->id, [
                'permission' => 'Member',
                'status' => Status::EMPLOYEE_PENDING->value,
            ]);
            if (!$membership->wasRecentlyCreated) {
                return MembershipResult::unchanged($membership, 'already_member'); // never reset
            }
            $membership->syncPermissions(['users_index', 'projects_index']);
            $membership->refresh();

            $settings = Utility::getAdminPaymentSetting();
            $payload = $membership;

            return MembershipResult::applied($membership, $membership->status, $provisioned->effects() + [
                'workspace_invite_notification' => fn () => Utility::sendNotification('workspace_invite', null, $user->id, $payload),
                'workspace_invite_email' => fn () => Utility::sendEmailTemplate('User Invited', $user->id, [
                    'user_name' => $user->name,
                    'app_name' => $settings['app_name'] ?? config('app.name'),
                    'workspace_name' => $workspace->name,
                    'owner_name' => $inviter->name,
                    'app_url' => config('app.url'),
                ]),
            ]);
        }, self::ATTEMPTS);

        return $this->afterCommit->run($result);
    }

    /** Owner approves a join request. */
    public function acceptJoinRequest(UserWorkspace $membership): MembershipResult
    {
        return $this->transition($membership, Status::WORKSPACE_PENDING, function (UserWorkspace $locked, User $user, Workspace $workspace) {
            $locked->forceFill(['status' => Status::ACTIVE->value, 'is_active' => 1])->save();
            Notification::where('type', 'workspace_join')->where('related_id', $locked->id)->update(['is_read' => 1]);
            $payload = $this->payload($locked);

            return ['workspace_request_accept_user' => fn () => Utility::sendNotification('workspace_request_accept_user', null, $user->id, $payload)];
        });
    }

    /** Owner declines a join request: the membership stays as a declined record without access. */
    public function declineJoinRequest(UserWorkspace $membership): MembershipResult
    {
        return $this->transition($membership, Status::WORKSPACE_PENDING, function (UserWorkspace $locked, User $user, Workspace $workspace) {
            $locked->forceFill(['status' => Status::DECLINED->value, 'is_active' => 0])->save();
            $locked->syncPermissions([]);
            Notification::where('type', 'workspace_join')->where('related_id', $locked->id)->update(['is_read' => 1]);
            $this->currentWorkspace->reassignIfLost($user, $workspace->id);
            $payload = $this->payload($locked);

            return ['workspace_request_decline_user' => fn () => Utility::sendNotification('workspace_request_decline_user', null, $user->id, $payload)];
        });
    }

    /** The invited user accepts their own invitation. */
    public function acceptInvitation(Workspace $workspace, User $user): MembershipResult
    {
        return $this->transitionFor($workspace, $user, function (UserWorkspace $locked) use ($workspace, $user) {
            $locked->forceFill(['status' => Status::ACTIVE->value, 'is_active' => 1])->save();
            Notification::where('type', 'workspace_invite')->where('related_id', $locked->id)->delete();
            $payload = $this->payload($locked);

            return [
                'workspace_request_accept_owner' => fn () => Utility::sendNotification('workspace_request_accept_owner', null, $workspace->created_by, $payload),
                'workspace_request_accept_user' => fn () => Utility::sendNotification('workspace_request_accept_user', null, $user->id, $payload),
            ];
        });
    }

    /** The invited user declines: the established workflow removes the membership. */
    public function declineInvitation(Workspace $workspace, User $user): MembershipResult
    {
        return $this->transitionFor($workspace, $user, function (UserWorkspace $locked) use ($workspace, $user) {
            $payload = $this->payload($locked);
            Notification::where('type', 'workspace_invite')->where('related_id', $locked->id)->delete();
            $locked->delete(); // Spatie's deleting hooks remove its permissions and roles
            $this->currentWorkspace->reassignIfLost($user, $workspace->id);

            return ['workspace_request_decline_owner' => fn () => Utility::sendNotification('workspace_request_decline_owner', null, $workspace->created_by, $payload)];
        });
    }

    private function transitionFor(Workspace $workspace, User $user, \Closure $apply): MembershipResult
    {
        $membership = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $user->id)->first();
        if (!$membership) {
            return MembershipResult::unchanged(null, 'not_member');
        }

        return $this->transition($membership, Status::EMPLOYEE_PENDING, fn (UserWorkspace $locked) => $apply($locked));
    }

    /** Lock the membership, require $from, apply the change; effects run after commit. */
    private function transition(UserWorkspace $membership, Status $from, \Closure $apply): MembershipResult
    {
        $result = DB::transaction(function () use ($membership, $from, $apply) {
            $locked = UserWorkspace::whereKey($membership->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== $from->value) {
                return MembershipResult::unchanged($locked, 'already_handled');
            }

            $workspace = Workspace::findOrFail($locked->workspace_id);
            if ($workspace->isPartOfPod()) {
                return MembershipResult::unchanged($locked, 'pod_workspace');
            }
            $user = User::findOrFail($locked->user_id);

            $effects = $apply($locked, $user, $workspace);

            return MembershipResult::applied($locked->exists ? $locked : null, $locked->exists ? $locked->status : null, $effects);
        }, self::ATTEMPTS);

        return $this->afterCommit->run($result);
    }

    /** Same payload shape the notification views read (a user_workspaces row). */
    private function payload(UserWorkspace $membership): object
    {
        return (object) array_intersect_key($membership->getAttributes(), array_flip(['id', 'user_id', 'workspace_id', 'permission', 'is_active', 'status', 'created_at', 'updated_at']));
    }
}

<?php

namespace App\Services\Membership;

use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\Mail\SendWorkspaceInvication;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Services\ChatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Project invitations and project claims (user_projects), each in one transaction (UNA-005).
 * Chat invitations, notifications and emails run after commit and only for what this call
 * actually created or resolved.
 */
class ProjectMembershipService
{
    public const ATTEMPTS = 3;
    private const PENDING = 'pending';

    public function __construct(private ?AfterCommit $afterCommit = null)
    {
        $this->afterCommit ??= new AfterCommit();
    }

    /**
     * Invite $user to $project: workspace membership (if missing) and project membership
     * together. Existing memberships are never reset or downgraded.
     */
    public function invite(User $user, Project $project, string $permission, User $invitedBy): MembershipResult
    {
        $result = DB::transaction(function () use ($user, $project, $permission, $invitedBy) {
            $workspaceMembership = UserWorkspace::findOrCreateMembership($user->id, (int) $project->workspace, ['permission' => $permission]);
            $projectMembership = UserProject::findOrCreateMembership($user->id, $project->id, [
                'permission' => json_encode(Utility::getAllPermission()),
            ]);

            $effects = [];
            if ($workspaceMembership->wasRecentlyCreated) {
                $effects['workspace_invitation_email'] = fn () => Mail::to($user->email)->send(new SendWorkspaceInvication($user, $project->workspaceData));
            }
            if ($projectMembership->wasRecentlyCreated) {
                $effects['project_chat_invite'] = fn () => (new ChatService())->inviteUserBySlug('project-' . $project->id, $user->id);
                if ($permission !== 'Owner') {
                    $effects['project_assign_notification'] = fn () => Utility::sendNotification('project_assign', $project->workspaceData, $user->id, $project);
                    $effects['project_assign_email'] = fn () => Utility::sendEmailTemplate('Project Assigned', $user->id, [
                        'user_name' => $user->name,
                        'app_name' => Utility::fetchAdminPaymentSetting()['app_name'] ?? config('app.name'),
                        'owner_name' => $invitedBy->name,
                        'project_name' => $project->name,
                        'project_status' => $project->status,
                        'app_url' => config('app.url'),
                    ]);
                }
            }

            return $projectMembership->wasRecentlyCreated || $workspaceMembership->wasRecentlyCreated
                ? MembershipResult::applied($projectMembership, $projectMembership->status, $effects)
                : MembershipResult::unchanged($projectMembership, 'already_member');
        }, self::ATTEMPTS);

        return $this->afterCommit->run($result);
    }

    /**
     * Submit a claim (join request) for $project as $user. Eligibility (an active membership of
     * the project's workspace) is re-checked under lock; an existing claim or membership of any
     * status is returned untouched (UNA-004).
     */
    public function submitClaim(Project $project, User $user): MembershipResult
    {
        $result = DB::transaction(function () use ($project, $user) {
            $workspace = $project->workspaceData;
            $eligible = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $user->id)
                ->where('status', WorkspaceStatus::ACTIVE->value)->lockForUpdate()->exists();
            if (!$eligible) {
                return MembershipResult::unchanged(null, 'not_workspace_member');
            }

            $claim = UserProject::findOrCreateMembership($user->id, $project->id, ['is_active' => 0, 'status' => self::PENDING]);
            if (!$claim->wasRecentlyCreated) {
                return MembershipResult::unchanged($claim, 'already_requested');
            }

            return MembershipResult::applied($claim, $claim->status, [
                'notify_owner' => fn () => Utility::sendNotification('project_request', null, $workspace->created_by, $claim),
            ]);
        }, self::ATTEMPTS);

        return $this->afterCommit->run($result);
    }

    /** Accept a pending claim exactly once: active membership with the full permission JSON. */
    public function acceptClaim(UserProject $claim): MembershipResult
    {
        return $this->resolveClaim($claim, [
            'is_active' => 1,
            'status' => 'active',
            'permission' => json_encode(Utility::getAllPermission()),
        ], 'project_accepted');
    }

    /** Decline a pending claim exactly once; a declined claim carries no access data. */
    public function declineClaim(UserProject $claim): MembershipResult
    {
        return $this->resolveClaim($claim, [
            'is_active' => 0,
            'status' => 'declined',
            'permission' => null,
        ], 'project_decline');
    }

    private function resolveClaim(UserProject $claim, array $attributes, string $notification): MembershipResult
    {
        $result = DB::transaction(function () use ($claim, $attributes, $notification) {
            $locked = UserProject::whereKey($claim->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== self::PENDING) {
                return MembershipResult::unchanged($locked, 'already_handled');
            }

            $changed = UserProject::whereKey($locked->id)->where('status', self::PENDING)->update($attributes);
            if ($changed !== 1) {
                return MembershipResult::unchanged($locked, 'already_handled');
            }
            $locked->refresh();

            // The pending-request card disappears with the transition (and comes back on rollback).
            Notification::where('type', 'project_request')->where('related_id', $locked->id)->delete();

            $project = Project::findOrFail($locked->project_id);
            $userId = $locked->user_id;

            return MembershipResult::applied($locked, $locked->status, [
                'notify_claimant' => fn () => Utility::sendNotification($notification, null, $userId, $project),
            ]);
        }, self::ATTEMPTS);

        return $this->afterCommit->run($result);
    }
}

<?php

namespace App\Services\Membership;

use App\Enums\PodUser\State;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\Notification;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\PodUserStateHistory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * The only application write path for pod_users.state (UNA-006).
 *
 * One call = lock the membership, validate the transition (State::transitions()) and reason,
 * conditionally update the state, synchronize workspace access FROM the state, append one
 * history row, and return the after-commit notifications. transition()/enter() own their
 * transaction; apply()/start() participate in the caller's (e.g. PodUserService) transaction.
 * The actor is always passed explicitly (null = system); Auth is never read here.
 */
class PodStateTransitionService
{
    public const ATTEMPTS = 3;

    /** Outer-circle baseline every active member holds. */
    public const BASELINE = [WorkspacePermission::USERS_INDEX, WorkspacePermission::NEWS_INDEX];

    /** Granted by the Inner Circle state (project visibility). */
    public const INNER_ACCESS = WorkspacePermission::PROJECTS_INDEX;

    /** Independent management grants kept across transitions while the member stays active. */
    public const MANAGEMENT = [
        WorkspacePermission::USERS_EDIT, WorkspacePermission::NEWS_EDIT,
        WorkspacePermission::ZOOM_MEETING_INDEX, WorkspacePermission::ZOOM_MEETING_EDIT,
        WorkspacePermission::PROJECTS_EDIT, // kept only with project visibility (Inner Circle)
    ];

    public function __construct(
        private ?AfterCommit $afterCommit = null,
        private ?CurrentWorkspace $currentWorkspace = null,
    ) {
        $this->afterCommit ??= new AfterCommit();
        $this->currentWorkspace ??= new CurrentWorkspace();
    }

    /** Transition an existing membership in its own transaction; effects run after commit. */
    public function transition(PodUser $membership, State $target, ?User $actor, ?string $reason = null): MembershipResult
    {
        return $this->afterCommit->run(DB::transaction(fn () => $this->apply($membership, $target, $actor, $reason), self::ATTEMPTS));
    }

    /**
     * Start (or restart, from rejected/removed) a membership in $initial state, in its own
     * transaction. An existing membership in any other state is returned unchanged.
     */
    public function enter(Pod $pod, User $user, State $initial, ?User $actor, ?string $reason = null, array $effects = []): MembershipResult
    {
        return $this->afterCommit->run(DB::transaction(fn () => $this->start($pod, $user, $initial, $actor, $reason, $effects), self::ATTEMPTS));
    }

    /** Inside a transaction: create the membership in $initial, or move a re-entry row there. */
    public function start(Pod $pod, User $user, State $initial, ?User $actor, ?string $reason = null, array $effects = []): MembershipResult
    {
        $membership = PodUser::findOrCreateMembership($user->id, $pod->id, ['permission' => 'Member', 'state' => $initial->value]);

        if ($membership->wasRecentlyCreated) {
            $this->syncAccess($membership, null, $initial);
            $this->record($membership, null, $initial, $actor, $this->normalize($reason));

            return MembershipResult::applied($membership, $initial->value, $this->effects($membership, null, $initial) + $effects);
        }

        if (in_array($membership->state, State::reentry(), true)) {
            $result = $this->apply($membership, $initial, $actor, $reason);

            return $result->applied ? MembershipResult::applied($result->membership, $result->state, $result->effects() + $effects) : $result;
        }

        return MembershipResult::unchanged($membership, 'already_member');
    }

    /**
     * Inside a transaction: validate and apply one transition. Throws InvalidStateTransition for a
     * forbidden transition, a missing reason or an owner; returns unchanged for a same-state
     * request ('no_change') or a lost race ('already_handled').
     */
    public function apply(PodUser $membership, State $target, ?User $actor, ?string $reason = null, ?array $allowedFrom = null): MembershipResult
    {
        $locked = PodUser::whereKey($membership->id)->lockForUpdate()->first()
            ?? throw new InvalidStateTransition('The membership no longer exists.');
        $from = $locked->state;

        if ($allowedFrom !== null && !in_array($from, $allowedFrom, true)) {
            return MembershipResult::unchanged($locked, 'already_handled');
        }
        if ($from === $target) {
            return MembershipResult::unchanged($locked, 'no_change');
        }
        if ($locked->isOwner()) {
            throw new InvalidStateTransition(__('The POD owner\'s membership cannot be changed.'));
        }
        if (!$from->canTransitionTo($target)) {
            throw new InvalidStateTransition(__('A member cannot move from :from to :to.', ['from' => $from->label(), 'to' => $target->label()]));
        }
        $reason = $this->normalize($reason);
        if ($reason === null && $target->requiresReasonFrom($from)) {
            throw new InvalidStateTransition(__('A reason is required for this change.'));
        }

        $changed = PodUser::whereKey($locked->id)->where('state', $from->value)
            ->update(['state' => $target->value] + ($target === State::REJECTED ? ['reason' => $reason] : []));
        if ($changed !== 1) {
            return MembershipResult::unchanged($locked, 'already_handled');
        }
        $locked->refresh();

        $this->syncAccess($locked, $from, $target);
        $this->record($locked, $from, $target, $actor, $reason);

        return MembershipResult::applied($locked, $target->value, $this->effects($locked, $from, $target));
    }

    // ------------------------------------------------------------------ access synchronization

    /** Make workspace access match $to. Workspace permissions follow the state, never the reverse. */
    private function syncAccess(PodUser $membership, ?State $from, State $to): void
    {
        $pod = Pod::findOrFail($membership->pod_id);
        $workspace = $pod->workspace ?? throw new \DomainException("POD {$pod->id} has no workspace.");
        $user = User::findOrFail($membership->user_id);

        if ($from !== null && in_array($from, State::applicants(), true)) {
            $this->deleteApplicantNotifications($pod, $membership);
        }

        if ($to === State::REMOVED) {
            $this->removeAccess($pod, $workspace, $membership, $user);

            return;
        }

        $userWorkspace = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $user->id)->lockForUpdate()->first()
            ?? UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Member', 'is_active' => 0, 'status' => WorkspaceStatus::EMPLOYEE_PENDING->value]);

        $profile = $to->accessProfile();
        if ($profile === 'none') {
            $status = in_array($to, State::applicants(), true) ? WorkspaceStatus::EMPLOYEE_PENDING : WorkspaceStatus::DECLINED;
            $userWorkspace->forceFill(['status' => $status->value, 'is_active' => 0])->save();
            $userWorkspace->syncPermissions([]);
            $this->currentWorkspace->reassignIfLost($user, $workspace->id);

            return;
        }

        $userWorkspace->forceFill(['status' => WorkspaceStatus::ACTIVE->value, 'is_active' => 1])->save();
        // Management grants carry over only between active states; entering access starts from the baseline.
        $wasActive = $from !== null && $from->accessProfile() !== 'none';
        $userWorkspace->syncPermissions($this->permissionsFor($profile, $wasActive ? $userWorkspace->getPermissionNames()->all() : []));
    }

    /**
     * Permissions for an active profile: the baseline, projects_index for Inner Circle only, and the
     * member's existing management grants (projects_edit only together with project visibility).
     *
     * @param string[] $current
     * @return string[]
     */
    public function permissionsFor(string $profile, array $current): array
    {
        $inner = $profile === 'inner';
        $kept = array_intersect($current, array_map(fn ($p) => $p->value, self::MANAGEMENT));
        if (!$inner) {
            $kept = array_diff($kept, [WorkspacePermission::PROJECTS_EDIT->value]);
        }

        return array_values(array_unique(array_merge(
            array_map(fn ($p) => $p->value, self::BASELINE),
            $inner ? [self::INNER_ACCESS->value] : [],
            $kept,
        )));
    }

    /** removed: drop workspace, project and task access (UNA-005 rules); the PodUser row stays. */
    private function removeAccess(Pod $pod, Workspace $workspace, PodUser $membership, User $user): void
    {
        $projectIds = Project::where('workspace', $workspace->id)->pluck('id');

        // Exact element match on the comma-separated assign_to list (same as FIND_IN_SET).
        Task::whereIn('project_id', $projectIds)->whereNotNull('assign_to')->lockForUpdate()->get()
            ->each(function (Task $task) use ($user) {
                $assignees = array_filter(explode(',', (string) $task->assign_to), fn ($id) => $id !== '');
                $remaining = array_values(array_filter($assignees, fn ($id) => (int) $id !== (int) $user->id));
                if (count($remaining) !== count($assignees)) {
                    $task->assign_to = implode(',', $remaining);
                    $task->save();
                }
            });

        $claimIds = UserProject::whereIn('project_id', $projectIds)->where('user_id', $user->id)->pluck('id')->all();
        UserProject::whereIn('id', $claimIds)->delete();
        if ($claimIds) {
            Notification::where('type', 'project_request')->whereIn('related_id', $claimIds)->delete();
        }

        $userWorkspace = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $user->id)->lockForUpdate()->first();
        if ($userWorkspace) {
            Notification::whereIn('type', ['workspace_invite', 'workspace_join'])->where('related_id', $userWorkspace->id)->delete();
            $userWorkspace->delete(); // Spatie's deleting hooks remove its permissions and roles
        }

        $this->deleteApplicantNotifications($pod, $membership);
        $this->currentWorkspace->reassignIfLost($user, $workspace->id);
    }

    private function deleteApplicantNotifications(Pod $pod, PodUser $membership): void
    {
        Notification::where('type', 'pod_request')->where('related_id', $membership->id)->delete();
        Notification::where('type', 'pod_invite')->where('user_id', $membership->user_id)->where('related_id', $pod->id)->delete();
    }

    // ------------------------------------------------------------------ history / effects

    private function record(PodUser $membership, ?State $from, State $to, ?User $actor, ?string $reason): void
    {
        PodUserStateHistory::query()->forceCreate([
            'pod_user_id' => $membership->id,
            'from_state' => $from?->value,
            'to_state' => $to->value,
            'actor_id' => $actor?->id,
            'reason' => $reason,
        ]);
    }

    /** @return array<string, \Closure> notifications for this transition (run after commit) */
    private function effects(PodUser $membership, ?State $from, State $to): array
    {
        $pod = Pod::findOrFail($membership->pod_id);
        $userId = (int) $membership->user_id;
        $fromApplicant = $from !== null && in_array($from, State::applicants(), true);
        $payload = $membership->fresh();

        $effects = match (true) {
            $to === State::MATCHED => ['notify_invitee' => fn () => Utility::sendNotification('pod_invite', null, $userId, $pod)],
            $to === State::PENDING_BUSINESS_APPROVAL => ['notify_owner' => fn () => Utility::sendNotification('pod_request', null, $pod->created_by, $payload)],
            $fromApplicant && $to === State::OUTER_CIRCLE => [
                'notify_owner' => fn () => Utility::sendNotification('pod_request_accept_owner', null, $pod->created_by, $payload),
                'notify_member' => fn () => Utility::sendNotification('pod_request_accept_user', null, $userId, $payload),
            ],
            $fromApplicant && $to === State::REJECTED => [
                'notify_owner' => fn () => Utility::sendNotification('pod_request_decline_owner', null, $pod->created_by, $payload),
                'notify_member' => fn () => Utility::sendNotification('pod_request_decline_user', null, $userId, $payload),
            ],
            $to === State::REMOVED => ['notify_removed' => fn () => Utility::sendNotification('pod_remove', null, $userId, $pod)],
            default => [],
        };

        if ($to === State::INNER_CIRCLE) {
            $effects['notify_inner_circle'] = fn () => Utility::sendNotification('pod_inner_circle_invite', null, $userId, $pod);
        }

        return $effects;
    }

    private function normalize(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }
}

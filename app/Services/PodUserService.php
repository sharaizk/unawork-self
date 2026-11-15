<?php

namespace App\Services;

use App\Enums\PodUser\State;
use App\Enums\UserWorkspace\Permission as UserWorkspacePermission;
use App\Jobs\SendEmail;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Services\Membership\AfterCommit;
use App\Services\Membership\InvalidStateTransition;
use App\Services\Membership\MembershipResult;
use App\Services\Membership\PodStateTransitionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Provisioning\DuplicateInvitation;
use App\Services\Provisioning\ProvisionUserData;
use App\Services\Provisioning\ProvisioningSource;
use App\Services\Provisioning\UserProvisioningService;

/**
 * POD membership workflows (UNA-005 transactions, UNA-006 explicit states).
 *
 * Every workflow owns one DB::transaction() (retried on deadlock) and changes pod_users.state only
 * through PodStateTransitionService, which also synchronizes workspace access and writes history.
 * Authorization happens in the controllers (UNA-001 policies); state is re-checked under locks.
 * External effects (notifications, emails) run after commit via AfterCommit.
 */
class PodUserService
{
    public const ATTEMPTS = 3;

    /** Permissions an editor may set directly; projects_index follows the circle state instead. */
    public const EDITABLE_PERMISSIONS = [
        UserWorkspacePermission::USERS_INDEX, UserWorkspacePermission::USERS_EDIT,
        UserWorkspacePermission::NEWS_INDEX, UserWorkspacePermission::NEWS_EDIT,
        UserWorkspacePermission::PROJECTS_EDIT,
        UserWorkspacePermission::ZOOM_MEETING_INDEX, UserWorkspacePermission::ZOOM_MEETING_EDIT,
    ];

    public function __construct(
        private ?AfterCommit $afterCommit = null,
        private ?PodStateTransitionService $states = null,
        private ?UserProvisioningService $provisioning = null,
    ) {
        $this->afterCommit ??= new AfterCommit();
        $this->states ??= new PodStateTransitionService($this->afterCommit);
        $this->provisioning ??= app(UserProvisioningService::class);
    }

    // ------------------------------------------------------------------ invitations / applications

    /**
     * Invite an existing user: matched (or re-entry from rejected/removed). UNA-012: only a
     * compatible employee can be invited (IncompatibleInvitee otherwise); a repeat throws
     * DuplicateInvitation and sends nothing.
     */
    public function inviteExistedUser(int $userId = null, int $podId, array $settings, ?User $actor = null): MembershipResult
    {
        $user = User::find($userId);
        if (is_null($user)) {
            throw new \Exception(__('This User is not connected with our system. Please fill out the below details to invite.'));
        }

        return $this->inviteByEmail(new ProvisionUserData(ProvisioningSource::POD_INVITATION, $user->email), Pod::findOrFail($podId), $settings, $actor);
    }

    /**
     * Invite by email (UNA-012): one transaction provisions the invitee (a new employee, or a
     * compatible existing employee) and creates the matched POD membership, its workspace access and
     * state history. New accounts are always employees; emails run after commit.
     */
    public function inviteNewUser(array $data, int $podId, array $settings, ?User $actor = null): MembershipResult
    {
        $password = $data['password'] ?? null;

        return $this->inviteByEmail(new ProvisionUserData(
            source: ProvisioningSource::POD_INVITATION,
            email: $data['email'],
            name: $data['name'] ?? null,
            password: $password ?: null,
            generatePassword: empty($password),
            sendLoginDetails: empty($password), // a generated password must reach the invitee
        ), Pod::findOrFail($podId), $settings, $actor);
    }

    private function inviteByEmail(ProvisionUserData $data, Pod $pod, array $settings, ?User $actor): MembershipResult
    {
        $result = $this->run(function () use ($data, $pod, $settings, $actor) {
            $provisioned = $this->provisioning->apply($data);
            $user = $provisioned->user;
            $effects = ['invitation_email' => fn () => $this->sendInvitationEmail($user, $pod, $settings)];
            if ($provisioned->created) {
                $effects['registration_email'] = fn () => $this->sendRegistrationEmail($user, $settings);
            }

            $result = $this->states->start($pod, $user, State::MATCHED, $actor,
                $provisioned->created ? 'Invited to the POD (new account)' : 'Invited to the POD', $effects + $provisioned->effects());

            return $result;
        });

        if (!$result->applied) {
            throw new DuplicateInvitation(__('This user is already a member of this pod.'));
        }

        return $result;
    }

    /** The applicant asks to join: pending_business_approval (or re-entry from rejected/removed). */
    public function requestJoinPod(int $userId, int $podId): MembershipResult
    {
        $pod = Pod::findOrFail($podId);
        $user = User::findOrFail($userId);

        $result = $this->run(fn () => $this->states->start($pod, $user, State::PENDING_BUSINESS_APPROVAL, $user, 'Applied to join the POD'));

        if (!$result->applied) {
            throw new \Exception('You have already submitted a join request for this pod.');
        }

        return $result;
    }

    public function getAllJoinRequests(int $podId)
    {
        return PodUser::where('pod_id', $podId)->where('state', State::PENDING_BUSINESS_APPROVAL->value)->get();
    }

    // ------------------------------------------------------------------ decisions

    /**
     * Accept (-> outer_circle) or decline (-> rejected) an invitation (matched) or application
     * (pending_business_approval) exactly once. A second or racing decision is 'already_handled'.
     *
     * @param State[]|null $allowedFrom restrict the source state (API moderation: applications only)
     */
    public function respondToMembership(Pod $pod, User $member, State $to, ?User $actor = null, ?array $allowedFrom = null, ?string $reason = null): MembershipResult
    {
        $membership = PodUser::where('pod_id', $pod->id)->where('user_id', $member->id)->first();
        if (!$membership) {
            return MembershipResult::unchanged(null, 'already_handled');
        }

        $reason ??= $to === State::REJECTED
            ? ($membership->state === State::MATCHED ? 'Invitation declined' : 'Application declined by the POD')
            : null;

        return $this->run(fn () => $this->states->apply($membership, $to, $actor, $reason, $allowedFrom ?? State::applicants()));
    }

    /** API moderation of an application; a reason is required to decline. */
    public function moderateJoinRequest(PodUser $request, string $action, ?string $reason = null, ?User $actor = null): MembershipResult
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
            $action === 'accept' ? State::OUTER_CIRCLE : State::REJECTED,
            $actor,
            [State::PENDING_BUSINESS_APPROVAL],
            $reason
        );
    }

    /** Generic validated transition (screening, suspension, resumption, ...). */
    public function changeState(Pod $pod, User $member, State $target, ?User $actor, ?string $reason = null): MembershipResult
    {
        $membership = PodUser::where('pod_id', $pod->id)->where('user_id', $member->id)->firstOrFail();

        return $this->run(fn () => $this->states->apply($membership, $target, $actor, $reason));
    }

    // ------------------------------------------------------------------ circle + permissions

    /**
     * Edit a member's workspace permissions and, optionally, move them between the Outer and Inner
     * Circle through an explicit $circle target. The circle is never inferred from the submitted
     * permissions: projects_index follows the state, the baseline is always kept for active
     * members, and projects_edit only exists with project visibility.
     */
    public function updatePermissions(Pod $pod, User $user, array $permissions, ?State $circle = null, ?User $actor = null, ?string $reason = null): MembershipResult
    {
        $editable = array_map(fn ($p) => $p->value, self::EDITABLE_PERMISSIONS);
        if (array_diff($permissions, array_merge($editable, [UserWorkspacePermission::PROJECTS_INDEX->value]))) {
            throw new \InvalidArgumentException(__('Invalid permission selected.'));
        }
        if ($circle !== null && !in_array($circle, [State::OUTER_CIRCLE, State::INNER_CIRCLE], true)) {
            throw new \InvalidArgumentException(__('Invalid circle selected.'));
        }

        return $this->run(function () use ($pod, $user, $permissions, $circle, $actor, $reason, $editable) {
            $membership = PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->lockForUpdate()->first()
                ?? throw new \Exception(__('This user is not a member of this pod workspace.'));

            $effects = [];
            if ($circle !== null && $circle !== $membership->state) {
                $reason ??= $circle === State::OUTER_CIRCLE ? 'Moved to the Outer Circle' : null;
                $effects = $this->states->apply($membership, $circle, $actor, $reason)->effects();
                $membership->refresh();
            }

            $profile = $membership->state->accessProfile();
            if ($profile === 'none') {
                throw new InvalidStateTransition(__('Permissions can only be edited for active members.'));
            }

            $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $pod->workspace->id)->lockForUpdate()->firstOrFail();
            $userWorkspace->syncPermissions($this->states->permissionsFor($profile, array_intersect($permissions, $editable)));

            return MembershipResult::applied($membership, $membership->state->value, $effects);
        });
    }

    // ------------------------------------------------------------------ removal / leave

    /** Manager removal or voluntary leave: -> removed. The row and its history are kept. */
    public function remove(int $userId, int $podId, ?User $actor = null, ?string $reason = null): MembershipResult
    {
        $pod = Pod::findOrFail($podId);
        $user = User::findOrFail($userId);
        $membership = PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->first();
        if (!$membership || $membership->state === State::REMOVED) {
            return MembershipResult::unchanged($membership, 'not_member');
        }

        $reason ??= ($actor && (int) $actor->id === (int) $user->id) ? 'Left the POD' : 'Removed by a POD manager';

        return $this->run(fn () => $this->states->apply($membership, State::REMOVED, $actor, $reason));
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

<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\PodUser;
use App\Models\User;
use App\Enums\PodUser\Permission;
use App\Enums\PodUser\Status;
use App\Enums\UserWorkspace\Permission as UserWorkspacePermission;
use App\Enums\UserWorkspace\Status as UserWorkspaceStatus;
use App\Jobs\SendEmail;
use App\Models\Project;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PodUserService
{
    public function updatePermissions(Pod $pod, User $user, array $permissions): bool
    {
        $workspace = $pod->workspace;

        // policies 
        $userWorkspaceEditor = UserWorkspace::where('user_id', Auth::id())->where('workspace_id', $workspace->id)->first();
        $podUserEditor = $pod->users()->where('user_id', Auth::id())->first();

        if (!$userWorkspaceEditor->hasPermissionTo(UserWorkspacePermission::USERS_EDIT->value) && $podUserEditor->pivot->permission != PodUserPermission::OWNER->value) {
            throw new \Exception(__('You do not have permission for this!'));
        }

        $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->first();
        $oldPermissions = $userWorkspace->getPermissionNames()->toArray();

        $userWorkspace->syncPermissions($permissions);

        if (!in_array('projects_index', $oldPermissions) && in_array('projects_index', $permissions)) {
            $this->sendNotificationJoinedInnerToUser($user, $pod);
        }
        
        return true;
    }

    protected function sendNotificationJoinedInnerToUser(User $user, Pod $pod)
    {
        Utility::sendNotification('pod_inner_circle_invite', null, $user->id, $pod);

        // $message = "You have been invited to join the pods inner circle: ".$pod->title." !";
        // $subject = 'Inner Circle Invitation';
        // $settings = Utility::getAdminPaymentSetting();

        // SendEmail::dispatch($user->email, $message, $subject, $settings);
    }

    public function remove(int $userId, int $podId): void
    {
        $pod = Pod::find($podId);
        $this->detachUserFromWorkspace($pod, $userId);
        $pod->users()->detach($userId);
        Utility::sendNotification('pod_remove', null, $userId, $pod);
    }

    public function inviteExistedUser(int $userId = null, int $podId, array $settings): void
    {
        $user = User::find($userId);
        $pod = Pod::find($podId);

        if (is_null($user)) {
            throw new \Exception(__('This User is not connected with our system. Please fill out the below details to invite.'));
        }

        if ($this->isUserAssignedToPod($user, $podId)) {
            throw new \Exception(__('This user is already a member of this pod.'));
        }

        $this->attachUserToPod($user, $podId);
        $this->sendInvitationalNotify($userId, $pod);
        $this->attachUserToPodWorkspaces($user, $pod);
    
        try {
            $this->sendInvitationEmail($user, $pod, $settings);
        } catch (\Exception $e) {
            throw new \Exception(__('E-Mails has not been sent due to SMTP configuration'.$e));
        }
    }

    public function inviteNewUser(array $data, int $podId, array $settings): void
    {
        $user = $this->createNewUser($data);
        $this->attachUserToPod($user, $podId);
        $pod = Pod::find($podId);
        
        try {
            $this->sendInvitationEmail($user, $pod, $settings);
            $this->sendRegistrationEmail($user, $settings);
        } catch (\Exception $e) {
            throw new \Exception(__('E-Mails has not been sent due to SMTP configuration'));
        }
    }

    public function requestJoinPod(int $userId, int $podId): void
    {
        $exists = PodUser::where('user_id', $userId)
            ->where('pod_id', $podId)
            ->exists();

        if ($exists) {
            throw new \Exception('You have already submitted a join request for this pod.');
        }

        $podUser = PodUser::create([
            'user_id' => $userId,
            'pod_id' => $podId,
            'permission' => Permission::MEMBER->value,
            'status' => Status::POD_PENDING->value
        ]);
        
        $pod = Pod::find($podId);
        $user = User::find($userId);
        $this->attachUserToPodWorkspaces($user, $pod);

        $this->sendRequestNotify($podUser, $pod->created_by);
    }

    public function getAllJoinRequests(int $podId)
    {
        return PodUser::where('pod_id', $podId)->where('status', Status::POD_PENDING->value)->get();
    }

    public function moderateJoinRequest(int $requestId, string $action, ?string $reason = null): void
    {
        $request = PodUser::findOrFail($requestId);

        if ($request->status != Status::POD_PENDING->value) {
            throw new \Exception('You can moderate only pod pending requests');
        }

        if ($action === 'accept') {
            $request->status = Status::ACTIVE->value;
            $this->attachUserToPodWorkspaces($request->user, $request->pod);
            $request->pod->workspace->status = UserWorkspaceStatus::ACTIVE->value;
        } elseif ($action === 'decline') {
            if (is_null($reason)) {
                throw new \Exception('Reason is required for declining a request');
            }
            $request->status = Status::DECLINED->value;
            $request->reason = $reason;
        } else {
            throw new \Exception('Invalid action');
        }

        $request->save();
    }

    private function detachUserFromWorkspace(Pod $pod, int $userId): void
    {
        $workspace = $pod->workspace;

        self::detachUserFromWorkspaceProjects($workspace, $userId);

        $user = User::find($userId);
        if ($user->currant_workspace == $workspace->id) {
            $user->updateCurrentWorkspace();
        }

        $workspace->users()->detach($userId);
    }

    private function detachUserFromWorkspaceProjects(Workspace $workspace, int $userId): void
    {
        $projects = $workspace->projects;
        foreach ($projects as $project) {
                self::detachUserFromPodWorkspaceTasks($project, $userId);
                $project->users()->detach($userId);
        }
    }

    private function detachUserFromPodWorkspaceTasks(Project $project, int $userId): void
    {
        $tasks = $project->user_tasks($userId);
        foreach ($tasks as $task) {
            $taskUsers = explode(',', $task->assign_to);
            $taskUsers = array_diff($taskUsers, [$userId]);
            $task->assign_to = implode(',', $taskUsers);
            $task->save();
        }
    }

    private function createNewUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'plan' => NULL,
            'password' => Hash::make($data['password']), // should use bcrypt for hashing password
            'email_verified_at' => date('Y-m-d i:h:s'),
            'is_enable_login' => 1,
        ]);
    }
    
    private function isUserAssignedToPod(User $user, int $podId): bool
    {
        return $user->pods()
            ->where('pods.id', $podId)
            ->exists();
    }

    private function attachUserToPod(User $user, int $podId): void
    {
        $user->pods()->attach($podId, [
            'permission' => Permission::MEMBER->value,
            'status' => Status::EMPLOYEE_PENDING->value
        ]);
    }

    private function attachUserToPodWorkspaces(User $user, Pod $pod): void
    {
        $workspace = $pod->workspace;

        if (!$workspace->users()->where('user_id', $user->id)->exists()) {
            $workspace->users()->attach($user->id, ['permission' => 'Member', 'is_active' => 1, 'status' => UserWorkspaceStatus::EMPLOYEE_PENDING->value]);
        }

        $userWorkspace = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $user->id)->first();
        $userWorkspace->givePermissionTo('users_index');
        $userWorkspace->givePermissionTo('news_index');
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

    private function sendInvitationalNotify(int $userId, Pod $pod): void
    {
        Utility::sendNotification('pod_invite', null, $userId, $pod);
    }

    private function sendRequestNotify(PodUser $podUser, int $podCreator): void
    {
        Utility::sendNotification('pod_request', null, $podCreator, $podUser);
    }

    public function getBackUrl($ownId, $userId, $podId): string
    {
        if ($ownId != $userId) {
            $location = route('pods.users', ['podId' => $podId]);
        } else {
            $workspace = Workspace::where('created_by', $userId)->first();
            $location = route('change-workspace', ['id' => $workspace->id]);
        }
        return $location;
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
}

<?php

namespace App\Http\Controllers;

use App\Enums\PodUser\Permission as PodUserPermission;
use App\Enums\UserWorkspace\Permission as UserWorkspacePermission;
use App\Enums\PodUser\Status;
use App\Models\Pod;
use App\Models\User;
use App\Services\PodUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Enums\UserWorkspace\Status as UserWorkspaceStatus;
use App\Models\Notification;
use App\Models\PodUser;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use Spatie\Permission\Models\Permission;

class PodUserController extends Controller
{
    protected $podUserService;

    public function __construct(PodUserService $podUserService)
    {
        $this->podUserService = $podUserService;
    }

    public function index(int $podId)
    {
        $pod = Pod::findOrFail($podId);
        $this->authorize('viewMembers', $pod);

        try {
            $currentWorkspace = $pod->workspace;

            $users = $this->podUserService->list($pod, Auth::id(), $currentWorkspace->id);

            return view('pod.manage.users', compact('currentWorkspace', 'pod', 'users'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Permission denied']);
        }
    }

    public function create(int $podId)
    {
        $pod = Pod::findOrFail($podId);
        $this->authorize('manageMembers', $pod);

        return view('pod.manage.invite_form', compact('pod'));
    }

    public function checkUserExists(Request $request, $podId)
    {
        $pod = Pod::findOrFail($podId);
        $this->authorize('manageMembers', $pod);

        $email = $request->email;
        $registerUsers = User::where('email', '=', $email)->first();
        //$settings = Utility::getPaymentSetting(Auth::user()->currant_workspace);
        $settings = Utility::getAdminPaymentSetting();

        if ($registerUsers) {
            try {
                $this->podUserService->inviteExistedUser($registerUsers->id, $podId, $settings);
                return response()->json([
                    'code' => 200,
                    'status' => 'Success',
                    'message' => __('User Invited Successfully!') . ((isset($smtp_error)) ? ' <br> <span class="text-danger">' . $smtp_error . '</span>' : ''),
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'code' => 500,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'code' => 404,
                'status' => 'Error',
                'error' => __('This User is not connected with our system. Please fill out the below details to invite.'),
            ], 404);
        }
    }

    // public function remove($podId, $userId)
    // {
    //     $pod = Pod::findOrFail($podId);
    //     $user = User::findOrFail($userId);
    //     $workspace = $pod->workspace;


    //     if ($pod->users->contains($user)) {
    //         $pod->users()->detach($user->id);
    //         if ($workspace->users()->where('user_id', $user->id)->exists()) {
    //             $workspace->users()->detach($user->id);
    //         }
    //         return response()->json(['message' => 'User removed from pod successfully'], 200);
    //     }

    //     return response()->json(['error' => 'User not found in pod'], 404);
    // }

    public function remove($podId, $userId)
    {
        $pod = Pod::findOrFail($podId);
        $member = User::findOrFail($userId);
        $this->authorize('removeMember', [$pod, $member]);

        try {
            $this->podUserService->remove($userId, $podId);
            $location = $this->podUserService->getBackUrl(Auth::id(), $userId, $podId);
            return response()->json([
                'message' => 'User removed from pod successfully',
                'location' => $location
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed:'.$e], 404);
        }
    }

    public function accept(Pod $pod, User $user)
    {
        $this->authorize('acceptMember', [$pod, $user]);

        $ownId = Auth::user()->id;
        if (!$this->resolvePendingMembership($pod, $user, Status::ACTIVE, ['permission' => 'Member', 'is_active' => 1, 'status' => UserWorkspaceStatus::ACTIVE->value])) {
            return $this->alreadyHandledResponse();
        }
        $podUser = PodUser::where('user_id', $user->id)->where('pod_id', $pod->id)->first();

        Utility::sendNotification('pod_request_accept_owner', null, $pod->created_by, $podUser);
        Utility::sendNotification('pod_request_accept_user', null, $user->id, $podUser);

        $this->deleteNotification('pod_request', $ownId, $podUser->id);
        $this->deleteNotification('pod_invite', $user->id, $pod->id);

        return response()->json(['message' => 'User status updated to active'], 200);
    }

    public function decline(Pod $pod, User $user)
    {
        $this->authorize('declineMember', [$pod, $user]);

        $ownId = Auth::user()->id;
        if (!$this->resolvePendingMembership($pod, $user, Status::DECLINED, ['permission' => 'Member', 'is_active' => 0, 'status' => UserWorkspaceStatus::DECLINED->value])) {
            return $this->alreadyHandledResponse();
        }
        $podUser = PodUser::where('user_id', $user->id)->where('pod_id', $pod->id)->first();

        Utility::sendNotification('pod_request_decline_owner', null, $pod->created_by, $podUser);
        Utility::sendNotification('pod_request_decline_user', null, $user->id, $podUser);

        $this->deleteNotification('pod_request', $ownId, $podUser->id);
        $this->deleteNotification('pod_invite', $user->id, $pod->id);

        $user->updateCurrentWorkspace();

        return response()->json(['message' => 'User status updated to active'], 200);
    }

    public function editPermissions(Pod $pod, User $user)
    {
        $this->authorize('updateMemberPermissions', [$pod, $user]);

        $workspace = $pod->workspace;
        $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->first();
        $permissions = Permission::all();

        return view('pod.manage.edit_permissions', compact('permissions', 'user', 'pod', 'userWorkspace'));
    }

    public function updatePermissions(Request $request, Pod $pod, User $user)
    {
        $this->authorize('updateMemberPermissions', [$pod, $user]);

        $permissions = $request->input('permissions', []);

        try {
            $message = $this->podUserService->updatePermissions($pod, $user, $permissions);
            $message = __('Permissions updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Move a pending invitation/request to $to exactly once. The conditional update is the
     * concurrency boundary: a second (or racing) accept/decline changes nothing, creates no
     * row and sends no notifications. Never inserts a membership.
     */
    private function resolvePendingMembership(Pod $pod, User $user, Status $to, array $workspaceAttributes): bool
    {
        return DB::transaction(function () use ($pod, $user, $to, $workspaceAttributes) {
            $changed = PodUser::where('pod_id', $pod->id)
                ->where('user_id', $user->id)
                ->whereIn('status', [Status::POD_PENDING->value, Status::EMPLOYEE_PENDING->value])
                ->update(['status' => $to->value]);

            if ($changed !== 1) {
                return false;
            }

            $pod->workspace->users()->updateExistingPivot($user->id, $workspaceAttributes);

            return true;
        });
    }

    private function alreadyHandledResponse()
    {
        return response()->json(['message' => __('This membership request has already been handled.')], 409);
    }

    private function deleteNotification($type, $userId, $podUserId)
    {
        Notification::where('type', $type)
            ->where('user_id', $userId)
            ->whereJsonContains('data->id', $podUserId)
            ->delete();
    }
}

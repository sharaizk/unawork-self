<?php

namespace App\Http\Controllers;

use App\Enums\PodUser\Permission as PodUserPermission;
use App\Enums\PodUser\Status;
use App\Models\Pod;
use App\Models\User;
use App\Services\PodUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserWorkspace\Status as UserWorkspaceStatus;
use App\Models\Notification;
use App\Models\PodUser;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use Spatie\Permission\Models\Permission;

class PodUserController extends Controller
{
    public function index(int $podId)
    {
        $currentWorkspace = Auth::user()->currentWorkspace;
        $pod = Pod::find($podId);
        $users = $pod->users;
        return view('pod.manage.users', compact('currentWorkspace', 'pod', 'users'));
    }

    public function create(int $podId)
    {
        $pod = Pod::find($podId);
        return view('pod.manage.invite_form', compact('pod'));
    }

    public function checkUserExists(Request $request, $podId)
    {
        $email = $request->email;
        $registerUsers = User::where('email', '=', $email)->first(); 
        //$settings = Utility::getPaymentSetting(Auth::user()->currant_workspace);
        $settings = Utility::getAdminPaymentSetting();
        
        if ($registerUsers) {
            $podUserService = new PodUserService;
            try {
                $podUserService->inviteExistedUser($registerUsers->id, $podId, $settings);
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
        $podUserService = new PodUserService;
        try {
            $podUserService->remove($userId, $podId);
            return response()->json(['message' => 'User removed from pod successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed:'.$e], 404);
        }
    }

    public function accept(Pod $pod, User $user)
    {   
        $ownId = Auth::user()->id;
        $pod->users()->syncWithoutDetaching([$user->id => ['status' => Status::ACTIVE->value]]);
        $pod->workspace->users()->updateExistingPivot($user->id, ['permission' => 'Member', 'is_active' => 1, 'status' => UserWorkspaceStatus::ACTIVE->value]);
        $podUser = PodUser::where('user_id', $user->id)->where('pod_id', $pod->id)->first();
    
        Utility::sendNotification('pod_request_accept_owner', null, $pod->created_by, $podUser);
        Utility::sendNotification('pod_request_accept_user', null, $user->id, $podUser);

        $this->deleteNotification('pod_request', $ownId, $podUser->id);
        $this->deleteNotification('pod_invite', $user->id, $pod->id);
        
        return response()->json(['message' => 'User status updated to active'], 200);
    }

    public function decline(Pod $pod, User $user)
    {   
        $ownId = Auth::user()->id;
        $pod->users()->syncWithoutDetaching([$user->id => ['status' => Status::DECLINED->value]]);
        $pod->workspace->users()->updateExistingPivot($user->id, ['permission' => 'Member', 'is_active' => 0, 'status' => UserWorkspaceStatus::DECLINED->value]);
        $podUser = PodUser::where('user_id', $user->id)->where('pod_id', $pod->id)->first();
    
        Utility::sendNotification('pod_request_decline_owner', null, $pod->created_by, $podUser);
        Utility::sendNotification('pod_request_decline_user', null, $user->id, $podUser);

        $this->deleteNotification('pod_request', $ownId, $podUser->id);
        $this->deleteNotification('pod_invite', $user->id, $pod->id);
        
        return response()->json(['message' => 'User status updated to active'], 200);
    }

    public function editPermissions(Pod $pod, User $user)
    {   
        $workspace = $pod->workspace;
        $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->first();
        $permissions = Permission::all();

        return view('pod.manage.edit_permissions', compact('permissions', 'user', 'pod', 'userWorkspace'));
    }

    public function updatePermissions(Request $request, Pod $pod, User $user)
    {
        $workspace = $pod->workspace;

        // policies 
        $userWorkspaceEditor = UserWorkspace::where('user_id', Auth::id())->where('workspace_id', $workspace->id)->first();
        $podUserEditor = $pod->users()->where('user_id', Auth::id())->first();
        if (!$userWorkspaceEditor->hasPermissionTo('users_edit') && $podUserEditor->pivot->permission != PodUserPermission::OWNER->value ) {
            return 'error';
        }

        $permissions = $request->input('permissions', []);
        $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->first();
        $userWorkspace->syncPermissions($permissions);

        return redirect()->back()->with('success', 'Permissions updated successfully.');
    }

    private function deleteNotification($type, $userId, $podUserId)
    {
        Notification::where('type', $type)
            ->where('user_id', $userId)
            ->whereJsonContains('data->id', $podUserId)
            ->delete();
    }
}
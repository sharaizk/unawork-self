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

        // One transaction in the service; unexpected failures surface as 500 with nothing changed.
        $result = $this->podUserService->remove($member->id, $pod->id);
        if (!$result->applied) {
            return response()->json(['message' => __('User not found in pod')], 404);
        }

        return response()->json([
            'message' => 'User removed from pod successfully',
            'location' => $this->podUserService->getBackUrl(Auth::id(), $member->id, $pod->id),
        ], 200);
    }

    public function accept(Pod $pod, User $user)
    {
        $this->authorize('acceptMember', [$pod, $user]);

        $result = $this->podUserService->respondToMembership($pod, $user, Status::ACTIVE);

        return $result->applied
            ? response()->json(['message' => 'User status updated to active'], 200)
            : $this->alreadyHandledResponse();
    }

    public function decline(Pod $pod, User $user)
    {
        $this->authorize('declineMember', [$pod, $user]);

        $result = $this->podUserService->respondToMembership($pod, $user, Status::DECLINED);

        return $result->applied
            ? response()->json(['message' => 'User status updated to declined'], 200)
            : $this->alreadyHandledResponse();
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

        $permissions = (array) $request->input('permissions', []);

        try {
            // Promotion/demotion: one transaction in the service, notification after commit.
            $this->podUserService->updatePermissions($pod, $user, $permissions);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            // Business outcomes (not a member, not active); the transaction has rolled back.
            if ($e instanceof \Illuminate\Database\QueryException) {
                throw $e;
            }
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('Permissions updated successfully.'));
    }

    private function alreadyHandledResponse()
    {
        return response()->json(['message' => __('This membership request has already been handled.')], 409);
    }
}

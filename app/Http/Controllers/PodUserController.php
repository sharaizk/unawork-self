<?php

namespace App\Http\Controllers;

use App\Enums\PodUser\Status;
use App\Models\Pod;
use App\Models\User;
use App\Services\PodUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if ($registerUsers) {
            $podUserService = new PodUserService;
            try {
                $podUserService->inviteExistedUser($registerUsers->id, $podId);
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

    public function remove($podId, $userId)
    {
        $pod = Pod::findOrFail($podId);
        $user = User::findOrFail($userId);

        if ($pod->users->contains($user)) {
            $pod->users()->detach($user->id);
            return response()->json(['message' => 'User removed from pod successfully'], 200);
        }

        return response()->json(['error' => 'User not found in pod'], 404);
    }

    public function accept(Pod $pod, User $user)
    {   
        $userInPod = $pod->users()->where('user_id', $user->id)->exists();

        if ($userInPod) {
            $pod->users()->syncWithoutDetaching([$user->id => ['status' => Status::ACTIVE->value]]);
            
            return response()->json(['message' => 'User status updated to active'], 200);
        }
        return response()->json(['error' => 'User not found in pod'], 404);
    }

    public function decline(Pod $pod, User $user)
    {   
        $userInPod = $pod->users()->where('user_id', $user->id)->exists();

        if ($userInPod) {
            $pod->users()->syncWithoutDetaching([$user->id => ['status' => Status::DECLINED->value]]);
            
            return response()->json(['message' => 'User status updated to active'], 200);
        }
        return response()->json(['error' => 'User not found in pod'], 404);
    }
}
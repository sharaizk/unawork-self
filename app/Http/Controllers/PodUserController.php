<?php

namespace App\Http\Controllers;

use App\Enums\PodUser\Permission as PodUserPermission;
use App\Enums\UserWorkspace\Permission as UserWorkspacePermission;
use App\Enums\PodUser\State;
use App\Services\Membership\InvalidStateTransition;
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
                $this->podUserService->inviteExistedUser($registerUsers->id, $podId, $settings, Auth::user());
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

    public function remove(Request $request, $podId, $userId)
    {
        $pod = Pod::findOrFail($podId);
        $member = User::findOrFail($userId);
        $this->authorize('removeMember', [$pod, $member]);

        // One transaction in the service; unexpected failures surface as 500 with nothing changed.
        $result = $this->podUserService->remove($member->id, $pod->id, Auth::user(), $request->input('reason'));
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

        try {
            $result = $this->podUserService->respondToMembership($pod, $user, State::OUTER_CIRCLE, Auth::user());
        } catch (InvalidStateTransition $e) {
            // e.g. the employee's profile is below the minimum for an active circle (UNA-007)
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $result->applied
            ? response()->json(['message' => 'User status updated to active'], 200)
            : $this->alreadyHandledResponse();
    }

    public function decline(Pod $pod, User $user)
    {
        $this->authorize('declineMember', [$pod, $user]);

        $result = $this->podUserService->respondToMembership($pod, $user, State::REJECTED, Auth::user(), null, request()->input('reason'));

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
        $membership = \App\Models\PodUser::where('pod_id', $pod->id)->where('user_id', $user->id)->first();

        return view('pod.manage.edit_permissions', compact('permissions', 'user', 'pod', 'userWorkspace', 'membership'));
    }

    public function updatePermissions(Request $request, Pod $pod, User $user)
    {
        $this->authorize('updateMemberPermissions', [$pod, $user]);

        $permissions = (array) $request->input('permissions', []);
        // The circle is an explicit target (never inferred from the permissions submitted).
        $circle = $request->filled('circle_state') ? State::tryFrom((string) $request->input('circle_state')) : null;
        if ($request->filled('circle_state') && !in_array($circle, [State::OUTER_CIRCLE, State::INNER_CIRCLE], true)) {
            return redirect()->back()->with('error', __('Invalid circle selected.'));
        }

        try {
            // Promotion/demotion + permission edit: one transaction in the service, notification after commit.
            $this->podUserService->updatePermissions($pod, $user, $permissions, $circle, Auth::user(), $request->input('reason'));
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

    /**
     * Validated state transition for a member (screening, suspension, resumption, circle moves).
     * 422 for an unknown state, a forbidden transition or a missing reason; 409 if already handled.
     */
    public function changeState(Request $request, Pod $pod, User $user)
    {
        $this->authorize('changeMemberState', [$pod, $user]);

        $data = $request->validate([
            'state' => ['required', new \Illuminate\Validation\Rules\Enum(State::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->podUserService->changeState($pod, $user, State::from($data['state']), Auth::user(), $data['reason'] ?? null);
        } catch (InvalidStateTransition $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (!$result->applied) {
            return $result->reason === 'no_change'
                ? response()->json(['message' => __('No change.'), 'state' => $result->state], 200)
                : $this->alreadyHandledResponse();
        }

        return response()->json(['message' => __('Membership updated.'), 'state' => $result->state], 200);
    }

    private function alreadyHandledResponse()
    {
        return response()->json(['message' => __('This membership request has already been handled.')], 409);
    }
}

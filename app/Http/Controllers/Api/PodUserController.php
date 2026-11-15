<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PodUser\InviteNewUserRequest;
use App\Http\Requests\PodUser\UpdateRequest;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\Utility;
use App\Services\PodUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PodUserController extends Controller
{   
    protected $podUserService;

    public function __construct(PodUserService $podUserService)
    {
        $this->podUserService = $podUserService;
    }

    public function inviteExistedUser(Request $request, $podId): JsonResponse
    {
        $pod = Pod::findOrFail($podId);
        $this->authorize('manageMembers', $pod);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);
        // $settings = Utility::getPaymentSetting(Auth::user()->currant_workspace);
        $settings = Utility::getAdminPaymentSetting();
        
        try {
            $this->podUserService->inviteExistedUser($data['user_id'], $podId, $settings, $request->user());
        } catch (\App\Services\Provisioning\ProvisioningException $e) {
            throw $e; // UNA-012: already invited (409) or incompatible account (422)
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'User invited successfully'
        ]);
    }

    public function inviteNewUser(InviteNewUserRequest $request, $podId): JsonResponse
    {
        // Authorized in InviteNewUserRequest::authorize() (PodPolicy::manageMembers), before validation.
        $data = $request->validated();
        // $settings = Utility::getPaymentSetting(Auth::user()->currant_workspace);
        $settings = Utility::getAdminPaymentSetting();

        try {
            $this->podUserService->inviteNewUser($data, $podId, $settings, $request->user());
        } catch (\App\Services\Provisioning\ProvisioningException $e) {
            throw $e; // UNA-012: already invited (409) or incompatible account (422)
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'User invited successfully'
        ]);
    }

    public function join(Request $request, $podId): JsonResponse
    {
        $pod = Pod::findOrFail($podId);
        $this->authorize('requestToJoin', $pod);

        try {
            $userId = $request->user()->id;
            $this->podUserService->requestJoinPod($userId, $podId);

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Join request submitted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function listing($podId): JsonResponse
    {
        $pod = Pod::findOrFail($podId);
        $this->authorize('manageMembers', $pod);

        try {
            $requests = $this->podUserService->getAllJoinRequests($podId);

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'data' => $requests
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateRequest $request, $podId, $requestId): JsonResponse
    {
        // Authorized in UpdateRequest::authorize() (PodPolicy::moderateJoinRequest), which also
        // verifies that $requestId belongs to $podId, before validation.
        $data = $request->validated();

        try {
            $result = $this->podUserService->moderateJoinRequest(PodUser::findOrFail($requestId), $data['action'], $data['reason'] ?? null, $request->user());

            if (!$result->applied) {
                return response()->json([
                    'code' => 409,
                    'status' => 'error',
                    'message' => __('This membership request has already been handled.'),
                ], 409);
            }

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Join request moderated successfully'
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            throw $e; // unexpected database failure: 500, transaction already rolled back
        } catch (\App\Services\Membership\InvalidStateTransition $e) {
            // a domain rule refused the change (e.g. incomplete employee profile, UNA-007); nothing changed
            return response()->json([
                'code' => 422,
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
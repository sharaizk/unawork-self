<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PodUser\InviteNewUserRequest;
use App\Http\Requests\PodUser\UpdateRequest;
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
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);
        // $settings = Utility::getPaymentSetting(Auth::user()->currant_workspace);
        $settings = Utility::getAdminPaymentSetting();
        
        try {
            $this->podUserService->inviteExistedUser($data['user_id'], $podId, $settings);
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
        $data = $request->validated();
        // $settings = Utility::getPaymentSetting(Auth::user()->currant_workspace);
        $settings = Utility::getAdminPaymentSetting();

        try {
            $this->podUserService->inviteNewUser($data, $podId, $settings);
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
        try {
            $userId = auth('sanctum')->user()->id; 
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
        $data = $request->validated();

        try {
            $this->podUserService->moderateJoinRequest($requestId, $data['action'], $data['reason'] ?? null);

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Join request moderated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
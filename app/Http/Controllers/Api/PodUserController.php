<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PodUser\InviteNewUserRequest;
use App\Models\Pod;
use App\Models\User;
use App\Services\PodUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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
    
        try {
            $this->podUserService->inviteExistedUser($data['user_id'], $podId);
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

        try {
            $this->podUserService->inviteNewUser($data, $podId);
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

    public function requestJoinPod(Request $request, $podId): JsonResponse
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

    public function viewJoinRequests($podId): JsonResponse
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

    public function moderateJoinRequest(Request $request, $requestId): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:accept,decline',
            'reason' => 'required_if:action,decline|string'
        ]);

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
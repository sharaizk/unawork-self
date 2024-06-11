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
            $this->podUserService->inviteExistedUser($data['email'] ?? null, $data['user_id'] ?? null, $podId);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => 'An error occurred while processing the request.'
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
}
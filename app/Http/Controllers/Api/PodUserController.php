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
            'email' => 'string|exists:users,email',
            'user_id' => 'integer|exists:users,id',
        ]);
    
        $response = $this->podUserService->inviteExistedUser($data['email'] ?? null, $data['user_id'] ?? null, $podId);
    
        return response()->json($response);
    }

    public function inviteNewUser(InviteNewUserRequest $request, $podId): JsonResponse
    {   
        $data = $request->validated();

        $response = $this->podUserService->inviteNewUser($data, $podId);

        return response()->json($response);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponser;
use App\Http\Requests\Auth\ApiRegisterRequest;
use App\Services\Provisioning\ProvisionUserData;
use App\Services\Provisioning\ProvisioningSource;
use App\Services\Provisioning\UserProvisioningService;


class AuthController extends Controller
{   
    use ApiResponser;

    /**
     * API sign-up (UNA-012): always an employee, provisioned with the full account structure in one
     * transaction. The token is issued only after that commits; a failure creates no token.
     * 201 on success; a duplicate email is a 422 field error, like web registration.
     */
    public function register(ApiRegisterRequest $request, UserProvisioningService $provisioning): JsonResponse
    {
        $result = $provisioning->provision(new ProvisionUserData(
            source: ProvisioningSource::API_REGISTRATION,
            email: $request->validated('email'),
            name: $request->validated('name'),
            password: $request->validated('password'),
            emailVerified: false,
        ));
        $user = $result->user;

        return $this->success([
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'account_type' => $result->role->value,
            'workspace_id' => $result->workspace->id,
            'onboarding_required' => $result->onboarding['required'],
            'onboarding_type' => $result->onboarding['type'],
            'completion_percentage' => $result->onboarding['percentage'],
            'onboarding_route' => $result->onboarding['route'],
            'onboarding_url' => $result->onboarding['url'],
        ], __('Account created successfully.'), 201);
    }

    public function login(Request $request): JsonResponse
    {   
        $attr = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        if (!Auth::attempt($attr)) {
            return response()->json(['error' => 'Credentials not match'], 401);
        }

        $user = Auth::user();

        return $this->success([
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => $user,
        ],'Logged in successfully.');
    }

    public function logout(): JsonResponse
    {
        auth()->user()->tokens()->delete();
        return $this->success([],'Tokens Revoked');
    }
}
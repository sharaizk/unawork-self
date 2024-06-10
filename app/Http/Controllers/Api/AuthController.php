<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Traits\ApiResponser;


class AuthController extends Controller
{   
    use ApiResponser;

    public function register(Request $request): JsonResponse
    {           
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
        ]);

        return $this->success([
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => $user,
        ],'Login successfully.');
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
        ],'Registered successfully.');
    }

    public function logout(): JsonResponse
    {
        auth()->user()->tokens()->delete();
        return $this->success([],'Tokens Revoked');
    }
}
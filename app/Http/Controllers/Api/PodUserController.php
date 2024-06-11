<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pod;
use App\Models\User;
use App\Services\PodUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PodUserController extends Controller
{   
    protected $podUserService;

    public function __construct(PodUserService $podUserService)
    {
        $this->podUserService = $podUserService;
    }

    public function inviteExistedUser(Request $request, $podId)
    {
        $data = $request->validate([
            'email' => 'string|exists:users,email',
            'user_id' => 'integer|exists:users,id',
        ]);
    
        $response = $this->podUserService->inviteExistedUser($data['email'] ?? null, $data['user_id'] ?? null, $podId);
    
        return json_encode($response);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreRequest;
use App\Http\Requests\Profile\UpdateRequest;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use App\Services\ProfileService;

class ProfileController extends Controller
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function index(): JsonResponse
    {
        $profile = auth('sanctum')->user()->profile;

        return response()->json($profile);
    }

    public function update(UpdateRequest $request, $profileId): JsonResponse
    {   
        $data = $request->validated();

        $profile = auth('sanctum')->user()->profile;

        try {
            $profile = $this->profileService->updateProfile($profile, $data);
            return response()->json($profile, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $profile = auth('sanctum')->user()->profile();
        
        $profile->delete();

        return response()->json(null, 204);
    }
}

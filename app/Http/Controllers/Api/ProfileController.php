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

    public function index()
    {
        $profile = auth('sanctum')->user()->profile;

        return response()->json($profile);
    }

    public function store(StoreRequest $request)
    {   
        $data = $request->validated();
        $user = auth('sanctum')->user();

        try {
            $profile = $this->profileService->createPod($user, $data);
            return response()->json($profile, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function update(UpdateRequest $request, $profileId): JsonResponse
    {   

        $data = $request->validated();
        $userId =auth('sanctum')->user()->id;
        $profile = Profile::find($profileId);

        return response()->json($profile);
        
        $profile->update($data);

        return response()->json($profile);
    }

    public function destroy(string $id)
    {
        $profile = auth('sanctum')->user()->profile();
        
        $profile->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateRequest;
use App\Services\ProfileService;

class EmployeeController extends Controller
{
    public function updateProfile(UpdateRequest $request)
    {
        $data = $request->validated();

        $profile = auth('sanctum')->user()->profile;

        try {
            $profileService = new ProfileService;
            $profileService->updateProfile($profile, $data);
            return redirect()->back()->with('success', __('Profile Updated Successfully!'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __($e->getMessage()));
        }
    }
}

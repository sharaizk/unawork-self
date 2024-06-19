<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateRequest;
use App\Services\ProfileService;

class EmployeeController extends Controller
{
    public function updateProfile(UpdateRequest $request)
    {
        $data = $request->validated();

        $user = auth('sanctum')->user();

        if ($user->profile->exists === false) {
            try {
                $profileService = new ProfileService;
                $profileService->createProfile($user, $data);
                return redirect()->back()->with('success', __('Profile Created and Updated Successfully!'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __($e->getMessage()));
            }
        } else {
            try {
                $profileService = new ProfileService;
                $profileService->updateProfile($user->profile, $data);
                return redirect()->back()->with('success', __('Profile Updated Successfully!'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __($e->getMessage()));
            }
        }
    }
}

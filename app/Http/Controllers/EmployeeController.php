<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateRequest;
use App\Models\User;
use App\Services\ProfileService;

class EmployeeController extends Controller
{
    public function show(User $employee)
    {
        return view('employee.public_profile', compact('employee'));
    }

    public function updateProfile(UpdateRequest $request)
    {        
        $data = $request->validated();
        $user = auth('sanctum')->user();
        $profileService = new ProfileService;
        if ($user->profile->exists === false) {
            try {
                $profileService->createProfile($user, $data);
                return redirect()->back()->with('success', __('Profile Created and Updated Successfully!'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __($e->getMessage()));
            }
        } else {
            try {
                $profileService->updateProfile($user->profile, $data);
                return redirect()->back()->with('success', __('Profile Updated Successfully!'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __($e->getMessage()));
            }
        }
    }
}

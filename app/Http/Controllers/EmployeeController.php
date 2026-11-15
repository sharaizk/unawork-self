<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Profile\ProfileCompletionService;

class EmployeeController extends Controller
{
    /** Public employee profile. Profile editing moved to /my-profile (MyProfileController, UNA-007). */
    public function show(User $employee)
    {
        $employee->load(array_merge(ProfileCompletionService::RELATIONS, [
            'profile.industry', 'profile.jobTitle', 'profile.preferredRoles.jobTitle', 'profile.experiences.jobTitle',
        ]));

        return view('employee.public_profile', compact('employee'));
    }
}

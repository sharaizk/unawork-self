<?php

namespace App\Http\Controllers;

use App\Enums\User\UserRole;
use App\Models\User;
use App\Models\Profile;
use App\Models\Skill;
use App\Filters\Employee\EmployeeFilter;
use App\Http\Requests\Employee\FilterRequest;

class HirePageController extends Controller
{
    public function index(FilterRequest $request)
    {   
        $countries = Profile::distinct()->pluck('country');
        $skills = Skill::all();

        $data = $request->all();
        $employees = User::role(UserRole::EMPLOYEE->value);

        $filter = new EmployeeFilter($data);
        $employees = $filter->apply($employees->with(['profile', 'skills']));
        $employees = $employees->get();


        return view('hiring.hiring', compact('employees', 'countries', 'skills'));
    }
}
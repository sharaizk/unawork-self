<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\Skill;
use App\Filters\Employee\EmployeeFilter;
use App\Http\Requests\Employee\FilterRequest;
use Illuminate\Http\Request;
use Chatify\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Auth;

class HirePageController extends Controller
{
    public function index(FilterRequest $request)
    {   
        $countries = Profile::distinct()->pluck('country');
        $skills = Skill::all();

        $data = $request->all();
        $employees = User::role('employee');

        $filter = new EmployeeFilter($data);
        $employees = $filter->apply($employees->with(['profile', 'skills']));
        $employees = $employees->get();


        return view('hiring.hiring', compact('employees', 'countries', 'skills'));
    }
}
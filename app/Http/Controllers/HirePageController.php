<?php

namespace App\Http\Controllers;

use App\Enums\User\UserRole;
use App\Models\User;
use App\Models\Profile;
use App\Models\Skill;
use App\Filters\Employee\EmployeeFilter;
use App\Http\Requests\Employee\FilterRequest;
use Illuminate\Http\Request;

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

    public function search(Request $request)
    {
        $query = $request->input('name', '');
        $country = $request->input('country', '');
        $jobTitleId = $request->input('job_title_id', '');
        $skillIds = $request->input('skill_ids', []);
        $perPage = 12;

        $employeesQuery = User::role(UserRole::EMPLOYEE->value)
            ->with(['profile', 'skills']);

        if (!empty($query)) {
            $employeesQuery->where('name', 'LIKE', "%$query%");
        }

        if (!empty($country)) {
            $employeesQuery->whereHas('profile', function ($q) use ($country) {
                $q->where('country', $country);
            });
        }

        if (!empty($jobTitleId)) {
            $employeesQuery->whereHas('profile', function ($q) use ($jobTitleId) {
                $q->where('job_title_id', $jobTitleId);
            });
        }

        if (!empty($skillIds)) {
            $employeesQuery->whereHas('skills', function ($q) use ($skillIds) {
                $q->whereIn('skills.id', $skillIds);
            });
        }

        $employees = $employeesQuery->paginate($perPage);

        return response()->json([
            'employees' => view('hiring.employee_list', compact('employees'))->render(),
            'pagination' => (string) $employees->appends(request()->query())->links()
        ]);
    }
}

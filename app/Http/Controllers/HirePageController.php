<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\Skill;
use App\Filters\Employee\EmployeeFilter;
use App\Http\Requests\Employee\FilterRequest;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HirePageController extends Controller
{
    public function index(FilterRequest $request)
    {
        $countries = Profile::distinct()->pluck('country');
        $skills = Skill::all();

        $data = $request->validated();
        $employees = User::employees();

        $filter = new EmployeeFilter($data);
        $employees = $filter->apply($employees->with(EmployeeService::LISTING_RELATIONS));
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
        // UNA-007 structured filters (validated; all optional).
        $structured = Arr::except($request->validate(FilterRequest::structuredRules()), ['job_title_id']);

        $employeesQuery = User::employees()
            ->with(EmployeeService::LISTING_RELATIONS);

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

        if (!empty($skillIds) && !empty($structured['skill_proficiency'])) {
            // the proficiency applies to the selected skills (handled by EmployeeFilter::skill_ids)
            $structured['skill_ids'] = (array) $skillIds;
        }
        (new EmployeeFilter($structured))->applyFilters($employeesQuery);

        $employees = $employeesQuery->paginate($perPage);

        return response()->json([
            'employees' => view('hiring.employee_list', compact('employees'))->render(),
            'pagination' => (string) $employees->appends(request()->query())->links('vendor.pagination.custom')
        ]);
    }
}

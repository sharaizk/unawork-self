<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\JobVacancyService;
use Illuminate\Support\Facades\Auth;

class JobVacancyController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;

        $vacancies = $user->jobVacancies;


        return view('job_vacancies.index', compact('currentWorkspace', 'user', 'vacancies'));
    }

    public function create()
    {
        $skills = Skill::all();
        return view('job_vacancies.manage.create_form', compact('skills'));
    }

    public function edit(JobVacancy $vacancy)
    {
        $skills = Skill::all();

        return view('job_vacancies.manage.edit_form', compact('vacancy', 'skills'));
    }

    public function show(JobVacancy $vacancy)
    {
        return view('job_vacancies.landing.page', compact('vacancy'));
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 12);

        $filters = [];

        if ($request->has('skill_ids')) {
            $filters['skills'] = $request->get('skill_ids');
        }

        $jobVacancyService = new JobVacancyService ();
        $jobVacancies = $jobVacancyService->list($filters, $perPage);

        $vacancies = $jobVacancies;
        $skills = Skill::all();

        $usersWithVacancies = User::whereHas('jobVacancies')
            ->whereHas('workspacesOwner', function ($query) use ($request) {
                if ($request->has('industry')) {
                    $query->where('industry_id', $request->industry)->orderBy('created_at')->take(1);
                }
            })
            ->get();

        return view('job_vacancies.landing.list', compact('vacancies', 'skills', 'usersWithVacancies'));
    }

    public function searchCompanies(Request $request)
    {
        $query = $request->input('query');

        $companies = User::whereHas('jobVacancies')
            ->whereHas('workspacesOwner', function ($queryBuilder) use ($request, $query) {
                if ($query) {
                    $queryBuilder->where('company', 'like', '%' . $query . '%');
                }
            })
            ->get()
            ->map(function ($company) {
                return $company->ownWorkspace() ?: null;
            })
            ->filter();

        return response()->json([
            'html' => view('job_vacancies.landing.company_list', [
                'usersWithVacancies' => $companies
            ])->render(),
        ]);
    }
}

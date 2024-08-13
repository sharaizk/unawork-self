<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use App\Models\Skill;
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
        return view('job_vacancies.landing.list', compact('vacancies', 'skills'));
    }
}
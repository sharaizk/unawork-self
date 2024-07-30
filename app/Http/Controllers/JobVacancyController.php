<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use Illuminate\Http\Request;
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
        return view('job_vacancies.manage.create_form');
    }

    public function edit(JobVacancy $vacancy)
    {
        return view('job_vacancies.manage.edit_form', compact('vacancy'));
    }
}
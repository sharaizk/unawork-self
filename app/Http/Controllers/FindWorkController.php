<?php

namespace App\Http\Controllers;

use App\Models\Pod;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use App\Enums\User\UserRole;
use App\Models\JobVacancy;
use App\Services\CompanyService;

class FindWorkController extends Controller
{
    public function index(Request $request)
    {   
        $companyService = new CompanyService;
        $companies = $companyService->getCompanies(null, 4);
        $countries = $companyService->getCompanies()->pluck('country')->filter()->unique()->values();
        $pods = Pod::limit(5)->get();
        $skills = Skill::all();
        $projects = Project::private(false)->limit(3)->get();

        $user = auth()->user();
        $joinedPods = [];
    
        if ($user) {
            $joinedPods = $user->pods()->pluck('pod_id')->toArray();
        }

        $vacancies = JobVacancy::limit(4)->get();

        return view('find_work.index', compact('vacancies', 'companies', 'pods', 'countries', 'skills', 'projects', 'joinedPods', 'user'));
    }
}

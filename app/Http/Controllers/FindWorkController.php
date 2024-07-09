<?php

namespace App\Http\Controllers;

use App\Models\Pod;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use App\Enums\User\UserRole;
use App\Services\CompanyService;

class FindWorkController extends Controller
{
    public function index(Request $request)
    {   
        $companyService = new CompanyService;
        $companies = $companyService->getCompanies(null, 4);
        $pods = Pod::limit(5)->get();
        $countries = Profile::distinct()->pluck('country');
        $skills = Skill::all();
        $projects = Project::private(false)->limit(3)->get();

        return view('find_work.index', compact('companies', 'pods', 'countries', 'skills', 'projects'));
    }
}

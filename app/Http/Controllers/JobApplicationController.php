<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobApplicationController extends Controller
{
    public function index(JobVacancy $vacancy)
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;

        $applications = $vacancy->jobApplications()->orderByRaw("CASE WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->get();


        return view('job_applications.index', compact('currentWorkspace', 'user', 'vacancy', 'applications'));
    }
}

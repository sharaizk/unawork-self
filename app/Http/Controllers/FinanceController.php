<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;

        $vacancies = $user->jobVacancies;


        return view('finance.index', compact('currentWorkspace', 'user', 'vacancies'));
    }
}

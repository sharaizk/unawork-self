<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PodController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;
        return view('users.pod', compact('currentWorkspace', 'user'));
    }
}

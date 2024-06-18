<?php

namespace App\Http\Controllers;

use App\Models\Pod;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PodController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;
        $pods = $user->pods;
        return view('users.pod', compact('currentWorkspace', 'user', 'pods'));
    }

    public function create()
    {
        $skills = Skill::all();
        return view('pod.create_form', compact('skills'));
    }

    public function edit(int $podId)
    {
        $pod = Pod::find($podId);
        $skills = Skill::all();
        return view('pod.edit_form', compact('pod', 'skills'));
    }

    public function show(int $podId)
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;
        $pod = Pod::find($podId);
        return view('pod.manage.index', compact('currentWorkspace', 'pod'));
    }
    
}

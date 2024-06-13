<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Request;
use Auth;

class EmployeeController extends Controller
{
    public function showProfile()
    {
        $employee = Auth::user();
        $profile = $employee->profile;
        $skills = $employee->skills;

        return view('employee.profile', compact('employee', 'profile', 'skills'));
    }

    public function updateProfile()
    {
        $employee = Auth::user();
        $profile = $employee->profile;
        $skills = Skill::all(); 

        return view('employee.edit_profile', compact('employee', 'profile', 'skills'));
    }

    public function showChats()
    {
        $employee = Auth::user();
        $chats = []; 
        return view('employee.chats', compact('employee', 'chats'));
    }
}

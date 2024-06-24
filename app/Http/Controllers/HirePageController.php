<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\Skill;
use Illuminate\Http\Request;
use Chatify\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Auth;

class HirePageController extends Controller
{
    public function index(Request $request)
    {   
        $countries = Profile::distinct()->pluck('country');
        $skills = Skill::distinct()->pluck('name');

        $query = User::role('employee')->with(['profile', 'skills']);

        if ($request->has('name') && $request->name != '') {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }
        
        if ($request->has('country') && $request->country != '') {
            $query->whereHas('profile', function($q) use ($request) {
                $q->where('country', $request->country);
            });
        }

        if ($request->has('skill') && $request->skill != '') {
            $query->whereHas('skills', function($q) use ($request) {
                $q->where('name', $request->skill);
            });
        }

        $employees = $query->get();

        return view('hiring.hiring', compact('employees', 'countries', 'skills'));
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Pod;
use App\Models\User;
use Illuminate\Http\Request;

class FindWorkController extends Controller
{
    public function index(Request $request)
    {   
        $companys = User::role('company')->limit(4)->get();
        $pods = Pod::limit(5)->get();
        return view('find_work.index', compact('companys', 'pods'));
    }
}

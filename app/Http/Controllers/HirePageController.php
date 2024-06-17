<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Chatify\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Auth;

class HirePageController extends Controller
{
    public function index()
    {
        $employees = User::role('employee')->with(['profile', 'skills'])->get();

        return view('hiring.hiring', compact('employees'));
    }
}
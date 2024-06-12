<?php

namespace App\Http\Controllers\Api;

use App\Filters\Employee\EmployeeFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $filter = new EmployeeFilter($request);
        $employees = $filter->apply(User::with(['profile', 'skills']));
        return EmployeeResource::collection($employees->get());
    }
}

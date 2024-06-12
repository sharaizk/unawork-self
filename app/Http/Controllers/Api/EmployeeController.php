<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ListRequest;
use App\Http\Resources\EmployeeResource;
use App\Services\EmployeeService;

class EmployeeController extends Controller
{
    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function index(ListRequest $request)
    {
        $data = $request->validated();
        $employees = $this->employeeService->getEmployees($data);
        return EmployeeResource::collection($employees);
    }
}

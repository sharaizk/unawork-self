<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\FilterRequest;
use App\Http\Resources\EmployeeResource;
use App\Services\EmployeeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Request;

class EmployeeController extends Controller
{
    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function index(FilterRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        $employees = $this->employeeService->getEmployees($data);

        return EmployeeResource::collection($employees);
    }

    public function show(Request $request, $id)
    {
        $employee = $this->employeeService->show($id);

        return new EmployeeResource($employee);
    }
}

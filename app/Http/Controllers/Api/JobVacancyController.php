<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\JobVacancy\StoreJobVacancyRequest;
use App\Http\Requests\JobVacancy\UpdateJobVacancyRequest;
use App\Models\JobVacancy;
use App\Services\JobVacancyService;
use Illuminate\Http\Request;

class JobVacancyController extends Controller
{
    protected $jobVacancyService;

    public function __construct(JobVacancyService $jobVacancyService)
    {
        $this->jobVacancyService = $jobVacancyService;
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $jobVacancies = $this->jobVacancyService->list($perPage);
        
        return response()->json($jobVacancies);
    }

    public function show(JobVacancy $jobVacancy)
    {
        return response()->json($jobVacancy);
    }

    public function store(StoreJobVacancyRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        $jobVacancy = $this->jobVacancyService->create($data);

        return response()->json($jobVacancy, 201);
    }

    public function update(UpdateJobVacancyRequest $request, JobVacancy $jobVacancy)
    {
        $this->authorize('update', $jobVacancy);
        $data = $request->validated();
        $jobVacancy = $this->jobVacancyService->update($jobVacancy, $data);

        return response()->json($jobVacancy);
    }

    public function destroy(JobVacancy $jobVacancy)
    {
        $this->authorize('delete', $jobVacancy);
        $this->jobVacancyService->delete($jobVacancy);

        return response()->json(null, 204);
    }
}

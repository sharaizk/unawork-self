<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\JobVacancy\StoreJobVacancyRequest;
use App\Http\Requests\JobVacancy\UpdateJobVacancyRequest;
use App\Models\JobVacancy;
use App\Services\JobVacancyService;

class JobVacancyController extends Controller
{
    protected $jobVacancyService;

    public function __construct(JobVacancyService $jobVacancyService)
    {
        $this->jobVacancyService = $jobVacancyService;
    }

    public function index()
    {
        $jobVacancies = JobVacancy::all();
        return response()->json($jobVacancies);
    }

    public function show($id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        return response()->json($jobVacancy);
    }

    public function store(StoreJobVacancyRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        $jobVacancy = $this->jobVacancyService->createJobVacancy($data);

        return response()->json($jobVacancy, 201);
    }

    public function update(UpdateJobVacancyRequest $request, JobVacancy $jobVacancy)
    {
        $this->authorize('update', $jobVacancy);
        $data = $request->validated();
        $jobVacancy = $this->jobVacancyService->updateJobVacancy($jobVacancy, $data);

        return response()->json($jobVacancy);
    }

    public function destroy(JobVacancy $jobVacancy)
    {
        $this->authorize('delete', $jobVacancy);
        $this->jobVacancyService->deleteJobVacancy($jobVacancy);

        return response()->json(null, 204);
    }
}

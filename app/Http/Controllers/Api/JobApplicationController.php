<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobApplication\StoreRequest;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use App\Services\JobApplicationService;
use Illuminate\Support\Facades\Auth;

class JobApplicationController extends Controller
{
    protected $jobApplicationService;

    public function __construct(JobApplicationService $jobApplicationService)
    {
        $this->jobApplicationService = $jobApplicationService;
    }

    public function index()
    {
        $userId = Auth::id();
        $vacancies = $this->jobApplicationService->getVacanciesWithApplications($userId);

        return response()->json(['vacancies' => $vacancies], 200);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        try {
            $application = $this->jobApplicationService->createApplication($data);
            return response()->json(['message' => 'Application submitted successfully', 'application' => $application], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
        
        return response()->json(['message' => 'Application submitted successfully', 'application' => $application], 201);
    }

    public function accept(JobApplication $jobApplication)
    {
        $this->authorize('update', $jobApplication);

        $application = $this->jobApplicationService->updateStatus($jobApplication->id, 'accepted');
        return response()->json(['message' => 'Application accepted', 'application' => $application], 200);
    }

    public function reject(JobApplication $jobApplication)
    {
        $this->authorize('update', $jobApplication);

        $application = $this->jobApplicationService->updateStatus($jobApplication->id, 'rejected');
        return response()->json(['message' => 'Application rejected', 'application' => $application], 200);
    }
}

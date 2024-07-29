<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobVacancy;
use Illuminate\Support\Facades\Auth;

class JobApplicationService
{
    public function getVacanciesWithApplications(int $userId)
    {
        return JobVacancy::where('user_id', $userId)->with('jobApplications')->get();
    }
    
    public function createApplication(array $data): JobApplication
    {
        $existingApplication = JobApplication::where('user_id', Auth::id())
            ->where('job_vacancy_id', $data['job_vacancy_id'])
            ->where('status', 'pending')
            ->first();

        if ($existingApplication) {
            throw new \Exception('You already have a pending application for this job vacancy.');
        }

        return JobApplication::create($data);
    }

    public function updateStatus(int $id, string $status): JobApplication
    {
        $application = JobApplication::findOrFail($id);
        $application->status = $status;
        $application->save();

        return $application;
    }
}
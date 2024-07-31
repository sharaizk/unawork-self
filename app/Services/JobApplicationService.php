<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Notification;
use App\Models\Utility;

class JobApplicationService
{
    public function getVacanciesWithApplications(int $userId)
    {
        return JobVacancy::where('user_id', $userId)->with('jobApplications')->get();
    }
    
    public function createApplication(array $data): JobApplication
    {
        $existingApplication = JobApplication::where('user_id', $data['user_id'])
            ->where('job_vacancy_id', $data['job_vacancy_id'])
            ->where('status', 'pending')
            ->first();

        if ($existingApplication) {
            throw new \Exception('You already have a pending application for this job vacancy.');
        }

        $application = JobApplication::create($data);

        try {
            $this->sendNewApplicationNotification($data['job_vacancy_id'], $application);
        } catch (\Exception $e) {
            throw new \Exception('Error on sending notification: ' . $e->getMessage());
        }
        
        return $application;
    }

    public function updateStatus(int $id, string $status): JobApplication
    {
        $application = JobApplication::findOrFail($id);
        $application->status = $status;
        $application->save();

        try {
            $this->sendNewStatusNotification($status, $application);
        } catch (\Exception $e) {
            throw new \Exception('Error on sending notification: ' . $e->getMessage());
        }

        return $application;
    }

    private function sendNewApplicationNotification(int $vacancyId, JobApplication $application): void
    {
        $vacancy = JobVacancy::find($vacancyId);
        $vacancyOwnerId = $vacancy->company->id;

        Utility::sendNotification('job_application_new', null, $vacancyOwnerId, $application);
    }

    private function sendNewStatusNotification(string $status, JobApplication $application): void
    {
        $oldNotify = Notification::where('type', 'job_application_new')->whereJsonContains('data->id', $application->id)->first();
        if ($oldNotify) {
            $oldNotify->delete();
        }
        
        Utility::sendNotification('job_application_'.$status, null, $application->user_id, $application);
    }

}
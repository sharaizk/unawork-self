<?php

namespace App\Services;

use App\Models\JobVacancy;

class JobVacancyService
{
    public function createJobVacancy(array $data): JobVacancy
    {
        return JobVacancy::create($data);
    }

    public function updateJobVacancy(JobVacancy $jobVacancy, array $data): JobVacancy
    {
        $jobVacancy->update($data);
        return $jobVacancy;
    }

    public function deleteJobVacancy(JobVacancy $jobVacancy): void
    {
        $jobVacancy->delete();
    }
}
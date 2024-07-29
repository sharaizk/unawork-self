<?php

namespace App\Services;

use App\Models\JobVacancy;

class JobVacancyService
{
    public function list(int $perPage = 10)
    {
        return JobVacancy::paginate($perPage);
    }

    public function create(array $data): JobVacancy
    {
        return JobVacancy::create($data);
    }

    public function update(JobVacancy $jobVacancy, array $data): JobVacancy
    {
        $jobVacancy->update($data);
        return $jobVacancy;
    }

    public function delete(JobVacancy $jobVacancy): void
    {
        $jobVacancy->delete();
    }
}
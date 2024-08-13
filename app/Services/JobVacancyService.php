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
        $jobVacancy = JobVacancy::create($data);

        if (isset($data['skills'])) {
            $jobVacancy->skills()->sync($data['skills']);
        }

        return $jobVacancy;
    }

    public function update(JobVacancy $jobVacancy, array $data): JobVacancy
    {
        $jobVacancy->update($data);

        if (isset($data['skills'])) {
            $jobVacancy->skills()->sync($data['skills']);
        }

        return $jobVacancy;
    }

    public function delete(JobVacancy $jobVacancy): void
    {
        $jobVacancy->delete();
    }
}
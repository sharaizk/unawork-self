<?php

namespace App\Policies;

use App\Contracts\HasAccountType;
use App\Models\JobTitle;

class JobTitlePolicy
{
    /**
     * Determine if the user can view the list of job titles.
     */
    public function viewAny(HasAccountType $user)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine if the user can create job titles.
     */
    public function create(HasAccountType $user)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine if the user can update the job title.
     */
    public function update(HasAccountType $user, JobTitle $jobTitle)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine if the user can delete the job title.
     */
    public function delete(HasAccountType $user, JobTitle $jobTitle)
    {
        return $user->isAdministrator();
    }
}
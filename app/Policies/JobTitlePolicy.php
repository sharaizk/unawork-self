<?php

namespace App\Policies;

use App\Models\User;
use App\Models\JobTitle;

class JobTitlePolicy
{
    /**
     * Determine if the user can view the list of job titles.
     */
    public function viewAny(User $user)
    {
        return $user->type == 'admin';
    }

    /**
     * Determine if the user can create job titles.
     */
    public function create(User $user)
    {
        return $user->type == 'admin';
    }

    /**
     * Determine if the user can update the job title.
     */
    public function update(User $user, JobTitle $jobTitle)
    {
        return $user->type == 'admin';
    }

    /**
     * Determine if the user can delete the job title.
     */
    public function delete(User $user, JobTitle $jobTitle)
    {
        return $user->type == 'admin';
    }
}
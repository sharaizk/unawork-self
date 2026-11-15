<?php

namespace App\Policies;

use App\Contracts\HasAccountType;
use App\Models\Industry;

class IndustryPolicy
{
    /**
     * Determine if the user can view the list of industries.
     */
    public function viewAny(HasAccountType $user)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine if the user can create industries.
     */
    public function create(HasAccountType $user)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine if the user can update the industry.
     */
    public function update(HasAccountType $user, Industry $industry)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine if the user can delete the industry.
     */
    public function delete(HasAccountType $user, Industry $industry)
    {
        return $user->isAdministrator();
    }
}
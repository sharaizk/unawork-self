<?php

namespace App\Policies;

use App\Contracts\HasAccountType;
use App\Models\Skill;
use Illuminate\Auth\Access\HandlesAuthorization;

class SkillPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any skillss.
     */
    public function viewAny(HasAccountType $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can view the skills.
     */
    public function view(HasAccountType $user, Skill $skill): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can create skillss.
     */
    public function create(HasAccountType $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can update the skills.
     */
    public function update(HasAccountType $user, Skill $skill): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can delete the skills.
     */
    public function delete(HasAccountType $user, Skill $skill): bool
    {
        return $user->isAdministrator();
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Skill;
use Illuminate\Auth\Access\HandlesAuthorization;

class SkillPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any skillss.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can view the skills.
     */
    public function view(User $user, Skill $skill): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can create skillss.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can update the skills.
     */
    public function update(User $user, Skill $skill): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can delete the skills.
     */
    public function delete(User $user, Skill $skill): bool
    {
        return $user->isAdministrator();
    }
}

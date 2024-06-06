<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Skills;
use Illuminate\Auth\Access\HandlesAuthorization;

class SkillsPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any skillss.
     */
    public function viewAny(User $user): bool
    {
        return $user->type === 'admin';
    }

    /**
     * Determine whether the user can view the skills.
     */
    public function view(User $user, Skills $skills): bool
    {
        return $user->type === 'admin';
    }

    /**
     * Determine whether the user can create skillss.
     */
    public function create(User $user): bool
    {
        return $user->type === 'admin';
    }

    /**
     * Determine whether the user can update the skills.
     */
    public function update(User $user, Skills $skills): bool
    {
        return $user->type === 'admin';
    }

    /**
     * Determine whether the user can delete the skills.
     */
    public function delete(User $user, Skills $skills): bool
    {
        return $user->type === 'admin';
    }
}

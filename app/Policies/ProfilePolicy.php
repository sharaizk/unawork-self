<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

/** Employee profiles (UNA-007): only the employee who owns a profile may edit it. */
class ProfilePolicy
{
    /** Employees edit their own profile on /my-profile; no other account type has one. */
    public function editOwn(User $user): bool
    {
        return $user->isEmployee();
    }

    public function update(User $user, Profile $profile): bool
    {
        return $user->isEmployee() && (int) $profile->user_id === (int) $user->id;
    }
}

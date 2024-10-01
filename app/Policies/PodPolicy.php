<?php

namespace App\Policies;

use App\Models\Pod;
use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Auth\Access\Response;

class PodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    // public function viewAny(User $user): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can view the model.
     */
    // public function view(User $user, Pod $pod): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can create models.
     */
    // public function create(User $user): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Pod $pod): bool
    {
        return $user->id === $pod->created_by;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Pod $pod): bool
    {
        return $user->id === $pod->created_by;
    }

    /**
     * Determine whether the user can restore the model.
     */
    // public function restore(User $user, Pod $pod): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can permanently delete the model.
     */
    // public function forceDelete(User $user, Pod $pod): bool
    // {
    //     //
    // }

    public function viewInner(User $user, Pod $pod): bool
    {
        $workspace = $pod->workspace;
        $userWorkspace = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->first();

        return $userWorkspace && $userWorkspace->hasPermissionTo('projects_index');
    }
}

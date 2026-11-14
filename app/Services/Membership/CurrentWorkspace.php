<?php

namespace App\Services\Membership;

use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\Pod;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;

/**
 * Keeps users.currant_workspace (sic) pointing at a workspace the user can still access.
 * Call inside the same transaction that removes or declines the access.
 */
class CurrentWorkspace
{
    /**
     * If $user's current workspace is $lostWorkspaceId (or is no longer accessible), move it to
     * a fallback: the user's own non-POD workspace, else their oldest other active membership,
     * else null (no accessible workspace left). Returns the new value.
     */
    public function reassignIfLost(User $user, int $lostWorkspaceId): ?int
    {
        $user->refresh();
        $current = $user->currant_workspace ? (int) $user->currant_workspace : null;

        if ($current !== null && $current !== $lostWorkspaceId && $this->canAccess($user, $current)) {
            return $current;
        }

        $fallback = $this->fallbackFor($user, $lostWorkspaceId);
        $user->forceFill(['currant_workspace' => $fallback])->save();

        return $fallback;
    }

    public function fallbackFor(User $user, int $excludedWorkspaceId): ?int
    {
        $own = Workspace::where('created_by', $user->id)
            ->where('id', '!=', $excludedWorkspaceId)
            ->where(fn ($q) => $q->whereNull('ownerable_type')->orWhere('ownerable_type', '!=', Pod::class))
            ->orderBy('id')
            ->pluck('id')
            ->first(fn ($id) => $this->canAccess($user, (int) $id));

        if ($own) {
            return (int) $own;
        }

        $membership = UserWorkspace::where('user_id', $user->id)
            ->where('workspace_id', '!=', $excludedWorkspaceId)
            ->where('status', WorkspaceStatus::ACTIVE->value)
            ->orderBy('id')
            ->value('workspace_id');

        return $membership ? (int) $membership : null;
    }

    private function canAccess(User $user, int $workspaceId): bool
    {
        return UserWorkspace::where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->where('status', WorkspaceStatus::ACTIVE->value)
            ->exists();
    }
}

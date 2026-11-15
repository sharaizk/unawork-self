<?php

namespace App\Services\Provisioning;

use App\Enums\User\UserRole;
use App\Models\User;
use App\Models\Workspace;

/**
 * Outcome of UserProvisioningService::provision()/apply() (UNA-012).
 *
 * $temporaryPassword is set only when this call generated a password; it lives on this object
 * (never on the model) so the caller can hand it to an after-commit email, and it is excluded from
 * serialization. $onboarding is calculated from the onboarding services, never stored.
 */
final class ProvisioningResult
{
    /** @param array<string, \Closure> $effects named after-commit effects */
    public function __construct(
        public readonly User $user,
        public readonly bool $created,
        public readonly ProvisioningSource $source,
        public readonly UserRole $role,
        public readonly Workspace $workspace,
        public readonly array $onboarding,
        #[\SensitiveParameter] private readonly ?string $temporaryPassword = null,
        private readonly array $effects = [],
    ) {
    }

    public function temporaryPassword(): ?string
    {
        return $this->temporaryPassword;
    }

    /** @return array<string, \Closure> */
    public function effects(): array
    {
        return $this->effects;
    }

    /** Safe summary for API responses and logs (no password). */
    public function toArray(): array
    {
        return [
            'user_id' => $this->user->id,
            'created' => $this->created,
            'source' => $this->source->value,
            'account_type' => $this->role->value,
            'workspace_id' => $this->workspace->id,
            'onboarding' => $this->onboarding,
        ];
    }

    public function __serialize(): array
    {
        return $this->toArray();
    }

    public function __debugInfo(): array
    {
        return $this->toArray();
    }
}

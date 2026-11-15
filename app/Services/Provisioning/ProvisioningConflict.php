<?php

namespace App\Services\Provisioning;

/** Administrator-created or imported account collides with an existing one; nothing is overwritten. */
class ProvisioningConflict extends ProvisioningException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('An account with this email address already exists.'));
    }

    public function status(): int
    {
        return 409;
    }

    public function field(): ?string
    {
        return 'email';
    }
}

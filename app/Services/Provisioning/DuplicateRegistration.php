<?php

namespace App\Services\Provisioning;

/** Registration (web, API, Fortify) for an email that already has an account. The existing account is never changed. */
class DuplicateRegistration extends ProvisioningException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('An account with this email address already exists. Please sign in or reset your password.'));
    }

    public function status(): int
    {
        return 422;
    }

    public function field(): ?string
    {
        return 'email';
    }
}

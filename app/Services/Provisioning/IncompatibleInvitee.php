<?php

namespace App\Services\Provisioning;

/** The invited email belongs to a company, administrator or unclassified account; it is never converted. */
class IncompatibleInvitee extends ProvisioningException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('This email belongs to an account that cannot be invited as an employee.'));
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

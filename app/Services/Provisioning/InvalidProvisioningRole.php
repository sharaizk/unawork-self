<?php

namespace App\Services\Provisioning;

/** A persona other than company/employee was requested, or one the source does not allow. */
class InvalidProvisioningRole extends ProvisioningException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Choose a company or employee account.'));
    }

    public function status(): int
    {
        return 422;
    }

    public function field(): ?string
    {
        return 'role';
    }
}

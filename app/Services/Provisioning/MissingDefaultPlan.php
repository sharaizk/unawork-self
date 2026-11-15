<?php

namespace App\Services\Provisioning;

/** The configured default plan does not exist; nothing was written. */
class MissingDefaultPlan extends ProvisioningException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Accounts cannot be created right now because the default plan is missing. Please contact the administrator.'));
    }

    public function status(): int
    {
        return 500;
    }

    public function field(): ?string
    {
        return null;
    }
}

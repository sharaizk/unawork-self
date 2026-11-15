<?php

namespace App\Services\Provisioning;

/** A repeated invitation: the existing membership is left untouched and no effects are sent. */
class DuplicateInvitation extends ProvisioningException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('This person has already been invited or is already a member.'));
    }

    public function status(): int
    {
        return 409;
    }

    public function field(): ?string
    {
        return null;
    }
}

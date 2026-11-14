<?php

namespace App\Contracts;

use App\Enums\User\AccountType;

/**
 * Canonical identity API shared by App\Models\User and App\Models\Client.
 *
 * Precedence: a Client is always CLIENT. A User is ADMINISTRATOR when users.type = admin
 * (roles are ignored), otherwise COMPANY when it holds the company role, otherwise
 * EMPLOYEE when it holds the employee role, otherwise unclassified (null).
 */
interface HasAccountType
{
    public function accountType(): ?AccountType;

    public function isAdministrator(): bool;

    /** A non-administrator web (users-table) account, i.e. users.type = user. Never a client. */
    public function isRegularUser(): bool;

    public function isCompany(): bool;

    public function isEmployee(): bool;

    public function isClient(): bool;
}

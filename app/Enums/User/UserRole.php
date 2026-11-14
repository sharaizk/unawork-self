<?php

namespace App\Enums\User;

/**
 * Spatie account roles (business persona) of a non-administrator User.
 * A User holds exactly one of these; assign it with User::assignAccountRole().
 */
enum UserRole: string
{
    case COMPANY = 'company';
    case EMPLOYEE = 'employee';

    /** Spatie guard the account roles are registered under (the users provider's guard). */
    public const GUARD = 'web';

    public function opposite(): self
    {
        return $this === self::COMPANY ? self::EMPLOYEE : self::COMPANY;
    }
}

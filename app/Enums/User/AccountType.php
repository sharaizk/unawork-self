<?php

namespace App\Enums\User;

/**
 * The single resolved identity of an authenticated account (User or Client).
 * Resolve it with HasAccountType::accountType(); never rebuild it from users.type or roles.
 */
enum AccountType: string
{
    case ADMINISTRATOR = 'administrator';
    case COMPANY = 'company';
    case EMPLOYEE = 'employee';
    case CLIENT = 'client';
}

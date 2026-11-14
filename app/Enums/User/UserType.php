<?php

namespace App\Enums\User;

/**
 * Storage values of users.type: the broad class of a web (users-table) account.
 * Business persona (company/employee) lives in Spatie roles, see UserRole.
 */
enum UserType: string
{
    case ADMIN = 'admin';
    case USER = 'user';
}

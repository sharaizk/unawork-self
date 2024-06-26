<?php

namespace App\Enums\User;

enum UserRole: string
{
    case COMPANY = 'company';
    case EMPLOYEE = 'employee';
}
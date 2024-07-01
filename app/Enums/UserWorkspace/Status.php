<?php

namespace App\Enums\UserWorkspace;

enum Status: string
{
    case ACTIVE = 'active';
    case WORKSPACE_PENDING = 'workspace_pending';
    case EMPLOYEE_PENDING = 'employee_pending';
    case DECLINED = 'declined';
}
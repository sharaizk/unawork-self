<?php

namespace App\Enums\PodUser;

enum Status: string
{
    case ACTIVE = 'active';
    case POD_PENDING = 'pod_pending';
    case EMPLOYEE_PENDING = 'employee_pending';
    case DECLINED = 'declined';
}
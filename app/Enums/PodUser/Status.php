<?php

namespace App\Enums\PodUser;

enum Status: string
{
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case DECLINED = 'declined';
}
<?php

namespace App\Enums\PodUser;

enum Permission: string
{
    case MEMBER = 'Member';
    case OWNER = 'Owner';
}
<?php

namespace App\Services\Membership;

/** A POD membership state change that the transition graph or its rules do not allow (UNA-006). */
class InvalidStateTransition extends \DomainException
{
}

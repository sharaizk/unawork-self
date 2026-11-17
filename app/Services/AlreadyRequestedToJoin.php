<?php

namespace App\Services;

/**
 * PodUserService::requestJoinPod() found an existing membership that cannot be (re)started
 * (UNA-017: the join API answers 409 instead of 500). Same message as before.
 */
class AlreadyRequestedToJoin extends \Exception
{
}

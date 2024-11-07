<?php

namespace App\Policies;

use App\Models\User;

class ZoomMeetingPolicy
{

    // Is user able to create zoom meeting (from plan)
    public function create(User $user)
    {
        $plan = $user->getPlan;

        return $plan && $plan->allow_zoom_meeting_creation;
    }
}

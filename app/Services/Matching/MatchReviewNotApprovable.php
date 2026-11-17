<?php

namespace App\Services\Matching;

/** Approval refused because the current match no longer qualifies or the pair is excluded (UNA-018, HTTP 422). */
class MatchReviewNotApprovable extends \DomainException
{
    public static function notQualified(): self
    {
        return new self(__('This match no longer meets the POD’s current requirements, so it cannot be approved. You can still reject it.'));
    }

    public static function excluded(): self
    {
        return new self(__('This employee is excluded from matching for this POD, so the match cannot be approved.'));
    }
}

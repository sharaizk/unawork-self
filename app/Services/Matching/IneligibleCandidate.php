<?php

namespace App\Services\Matching;

/** Only regular employee accounts with a saved profile can be matched (UNA-013). */
class IneligibleCandidate extends \DomainException
{
    public static function notEmployee(): self
    {
        return new self(__('Only employee accounts can be matched with a POD worker requirement.'));
    }

    public static function withoutProfile(): self
    {
        return new self(__('The employee has no profile to match.'));
    }
}

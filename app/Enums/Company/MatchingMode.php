<?php

namespace App\Enums\Company;

use App\Enums\Profile\HasOptions;

/** workspaces.matching_mode (UNA-010): how a company wants to find workers. */
enum MatchingMode: string
{
    use HasOptions;

    case MANUAL = 'manual';
    case ASSISTED = 'assisted';
    case AUTOMATIC = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => __('Manual'),
            self::ASSISTED => __('Assisted'),
            self::AUTOMATIC => __('Automatic'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MANUAL => __('Your company searches for and selects workers itself.'),
            self::ASSISTED => __('UnaWork recommends suitable workers, but your company decides.'),
            self::AUTOMATIC => __('Matching workflows may recommend or start eligible matches automatically, according to your preferences.'),
        };
    }
}

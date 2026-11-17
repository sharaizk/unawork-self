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

    /** UNA-014: also the default for new PODs (PodMatchingMode::fromCompanyPreference). */
    public function description(): string
    {
        return match ($this) {
            self::MANUAL => __('Your company searches for and selects workers itself. New PODs start in Recommend only mode.'),
            self::ASSISTED => __('UnaWork recommends suitable workers, but your company decides. New PODs start in pending-approval mode.'),
            self::AUTOMATIC => __('Qualified workers may be placed in the Outer Circle automatically. New PODs start in automatic mode.'),
        };
    }
}

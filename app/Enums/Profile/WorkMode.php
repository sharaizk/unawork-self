<?php

namespace App\Enums\Profile;

/** profiles.preferred_work_mode (UNA-007). */
enum WorkMode: string
{
    use HasOptions;

    case REMOTE = 'remote';
    case HYBRID = 'hybrid';
    case ONSITE = 'onsite';
    case FLEXIBLE = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::REMOTE => __('Remote'),
            self::HYBRID => __('Hybrid'),
            self::ONSITE => __('On-site'),
            self::FLEXIBLE => __('Flexible'),
        };
    }
}

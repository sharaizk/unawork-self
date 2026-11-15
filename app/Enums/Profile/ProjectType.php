<?php

namespace App\Enums\Profile;

/** profile_project_types.project_type (UNA-007). */
enum ProjectType: string
{
    use HasOptions;

    case SHORT_TERM = 'short_term';
    case LONG_TERM = 'long_term';
    case ONGOING = 'ongoing';
    case FIXED_SCOPE = 'fixed_scope';
    case HOURLY = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::SHORT_TERM => __('Short-term'),
            self::LONG_TERM => __('Long-term'),
            self::ONGOING => __('Ongoing'),
            self::FIXED_SCOPE => __('Fixed scope'),
            self::HOURLY => __('Hourly'),
        };
    }
}

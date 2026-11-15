<?php

namespace App\Enums\Profile;

/** profiles.availability_status (UNA-007). */
enum AvailabilityStatus: string
{
    use HasOptions;

    case IMMEDIATELY = 'immediately';
    case WITHIN_TWO_WEEKS = 'within_two_weeks';
    case WITHIN_ONE_MONTH = 'within_one_month';
    case SPECIFIC_DATE = 'specific_date';
    case UNAVAILABLE = 'unavailable';

    /** profiles.available_from is required only for a specific start date. */
    public function requiresDate(): bool
    {
        return $this === self::SPECIFIC_DATE;
    }

    public function label(): string
    {
        return match ($this) {
            self::IMMEDIATELY => __('Available immediately'),
            self::WITHIN_TWO_WEEKS => __('Within two weeks'),
            self::WITHIN_ONE_MONTH => __('Within one month'),
            self::SPECIFIC_DATE => __('From a specific date'),
            self::UNAVAILABLE => __('Not available'),
        };
    }
}

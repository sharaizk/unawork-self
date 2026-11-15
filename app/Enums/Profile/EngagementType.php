<?php

namespace App\Enums\Profile;

/** profiles.preferred_engagement_type and profile_experiences.engagement_type (UNA-007). */
enum EngagementType: string
{
    use HasOptions;

    case FULL_TIME = 'full_time';
    case PART_TIME = 'part_time';
    case CONTRACT = 'contract';
    case FREELANCE = 'freelance';
    case INTERNSHIP = 'internship';

    public function label(): string
    {
        return match ($this) {
            self::FULL_TIME => __('Full-time'),
            self::PART_TIME => __('Part-time'),
            self::CONTRACT => __('Contract'),
            self::FREELANCE => __('Freelance'),
            self::INTERNSHIP => __('Internship'),
        };
    }
}

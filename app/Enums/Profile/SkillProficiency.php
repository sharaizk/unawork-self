<?php

namespace App\Enums\Profile;

/** user_skills.proficiency (UNA-007). Legacy rows keep proficiency = null. */
enum SkillProficiency: string
{
    use HasOptions;

    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';
    case EXPERT = 'expert';

    public function label(): string
    {
        return match ($this) {
            self::BEGINNER => __('Beginner'),
            self::INTERMEDIATE => __('Intermediate'),
            self::ADVANCED => __('Advanced'),
            self::EXPERT => __('Expert'),
        };
    }
}

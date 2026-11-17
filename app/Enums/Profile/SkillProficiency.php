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

    /** Ordered scale used by POD matching (UNA-013): beginner 1 < intermediate 2 < advanced 3 < expert 4. */
    public function rank(): int
    {
        return match ($this) {
            self::BEGINNER => 1,
            self::INTERMEDIATE => 2,
            self::ADVANCED => 3,
            self::EXPERT => 4,
        };
    }

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

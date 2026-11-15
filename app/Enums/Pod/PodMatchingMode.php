<?php

namespace App\Enums\Pod;

use App\Enums\Company\MatchingMode;
use App\Enums\Profile\HasOptions;

/**
 * pods.matching_mode (UNA-011): how proposed matches for this POD are handled. Distinct from the
 * company-level preference (App\Enums\Company\MatchingMode), which only preselects it. No matching
 * engine exists yet; selecting automatic never changes a membership by itself.
 */
enum PodMatchingMode: string
{
    use HasOptions;

    case AUTOMATIC = 'automatic';
    case PENDING_APPROVAL = 'pending_approval';

    public function label(): string
    {
        return match ($this) {
            self::AUTOMATIC => __('Automatic'),
            self::PENDING_APPROVAL => __('Pending approval'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AUTOMATIC => __('Future matching workflows may start eligible matches automatically, according to this POD configuration.'),
            self::PENDING_APPROVAL => __('Every proposed match needs your company approval.'),
        };
    }

    /** Preselection from the company preference: automatic → automatic; manual/assisted → pending approval. */
    public static function fromCompanyPreference(?MatchingMode $preference): ?self
    {
        return match ($preference) {
            MatchingMode::AUTOMATIC => self::AUTOMATIC,
            MatchingMode::MANUAL, MatchingMode::ASSISTED => self::PENDING_APPROVAL,
            null => null,
        };
    }
}

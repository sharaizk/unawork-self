<?php

namespace App\Enums\Pod;

use App\Enums\Company\MatchingMode;
use App\Enums\Profile\HasOptions;

/**
 * pods.matching_mode (UNA-011, extended UNA-014): what happens to a qualified match of a published
 * POD. Applied only by App\Services\Matching\PodMatchingWorkflowService. The company preference
 * (App\Enums\Company\MatchingMode) only preselects it for new PODs.
 */
enum PodMatchingMode: string
{
    use HasOptions;

    case RECOMMEND_ONLY = 'recommend_only';
    case PENDING_APPROVAL = 'pending_approval';
    case AUTOMATIC = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::RECOMMEND_ONLY => __('Recommend only'),
            self::PENDING_APPROVAL => __('Match with pending approval'),
            self::AUTOMATIC => __('Automatic Outer Circle placement'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RECOMMEND_ONLY => __('Qualified candidates are shown as recommendations. Nobody is added to the POD.'),
            self::PENDING_APPROVAL => __('Qualified candidates are added as applicants awaiting your approval.'),
            self::AUTOMATIC => __('Qualified candidates with a complete profile are placed directly in the Outer Circle.'),
        };
    }

    /** Does this mode create or advance memberships for qualified matches? */
    public function actsOnMemberships(): bool
    {
        return $this !== self::RECOMMEND_ONLY;
    }

    /** Preselection from the company preference: manual → recommend only, assisted → pending approval, automatic → automatic. */
    public static function fromCompanyPreference(?MatchingMode $preference): ?self
    {
        return match ($preference) {
            MatchingMode::MANUAL => self::RECOMMEND_ONLY,
            MatchingMode::ASSISTED => self::PENDING_APPROVAL,
            MatchingMode::AUTOMATIC => self::AUTOMATIC,
            null => null,
        };
    }
}

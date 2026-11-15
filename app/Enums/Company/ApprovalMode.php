<?php

namespace App\Enums\Company;

use App\Enums\Profile\HasOptions;

/**
 * Company default approval preferences (UNA-010): workspaces.default_pod_application_approval and
 * default_inner_circle_approval. Stored preferences only: membership changes still go through
 * PodStateTransitionService and its eligibility rules; nothing is approved automatically yet.
 */
enum ApprovalMode: string
{
    use HasOptions;

    case MANUAL = 'manual';
    case AUTOMATIC = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => __('Manual approval'),
            self::AUTOMATIC => __('Automatic approval'),
        };
    }
}

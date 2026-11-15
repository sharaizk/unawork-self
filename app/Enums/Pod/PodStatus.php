<?php

namespace App\Enums\Pod;

/** pods.status (UNA-011). Only published PODs appear on public surfaces. */
enum PodStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
        };
    }
}

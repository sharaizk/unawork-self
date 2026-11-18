<?php

namespace App\Enums\PodDocument;

/**
 * Upload and processing states for POD document versions and materials (UNA-021).
 */
enum PodDocumentUploadStatus: string
{
    case READY = 'ready';
    case PENDING = 'pending';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::READY => __('Ready'),
            self::PENDING => __('Pending'),
            self::FAILED => __('Failed'),
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

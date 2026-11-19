<?php

namespace App\Enums\PodDocument;

/**
 * Text extraction processing status for POD Knowledge Base and onboarding materials (UNA-022).
 */
enum PodDocumentExtractionStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Extraction Pending'),
            self::PROCESSING => __('Processing Extraction'),
            self::COMPLETED => __('Text Extracted'),
            self::FAILED => __('Extraction Failed'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-warning-light text-warning',
            self::PROCESSING => 'bg-info-light text-info',
            self::COMPLETED => 'bg-success-light text-success',
            self::FAILED => 'bg-danger text-white',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

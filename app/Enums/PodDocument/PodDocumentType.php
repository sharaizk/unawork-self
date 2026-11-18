<?php

namespace App\Enums\PodDocument;

/**
 * Types of documents stored in a POD Knowledge Base (UNA-020).
 */
enum PodDocumentType: string
{
    case GUIDELINE = 'guideline';
    case SCREENING = 'screening';
    case ONBOARDING = 'onboarding';
    case POLICY = 'policy';
    case PROCESS = 'process';
    case TECHNICAL = 'technical';
    case GENERAL = 'general';

    public function label(): string
    {
        return match ($this) {
            self::GUIDELINE => __('Guideline'),
            self::SCREENING => __('Screening Brief'),
            self::ONBOARDING => __('Onboarding'),
            self::POLICY => __('Policy'),
            self::PROCESS => __('Process & Workflow'),
            self::TECHNICAL => __('Technical Spec'),
            self::GENERAL => __('General'),
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

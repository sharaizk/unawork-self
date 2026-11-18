<?php

namespace App\Enums\PodDocument;

/**
 * Status of an individual version of a POD Knowledge Base document (UNA-020).
 *
 * Rules:
 *  - DRAFT: newly created, editable by authorized business users.
 *  - PUBLISHED: active version. Immutable. Only one version per document can be PUBLISHED.
 *  - ARCHIVED: previously published version replaced by a newer published version. Immutable.
 */
enum PodDocumentVersionStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
            self::ARCHIVED => __('Archived'),
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

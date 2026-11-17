<?php

namespace App\Services\Matching;

use DateTimeInterface;
use DateTimeZone;

/**
 * Deterministic, offline location and timezone helpers for matching (UNA-013). No geocoding.
 *
 * normalize(): lower-case (multibyte), every run of characters that is not a letter or digit becomes
 * one space, trimmed. tokens(): a requirement location split on commas, each part normalized, empty
 * parts dropped, duplicates removed (order kept).
 */
class LocationNormalizer
{
    public static function normalize(?string $value): string
    {
        $value = mb_strtolower((string) $value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /** @return string[] */
    public static function tokens(?string $location): array
    {
        $tokens = [];
        foreach (explode(',', (string) $location) as $part) {
            $normalized = self::normalize($part);
            if ($normalized !== '' && !in_array($normalized, $tokens, true)) {
                $tokens[] = $normalized;
            }
        }

        return $tokens;
    }

    /** A known IANA identifier (backward-compatible aliases such as Asia/Calcutta included). */
    public static function isValidTimezone(?string $timezone): bool
    {
        static $identifiers = null;
        $identifiers ??= array_flip(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC));

        return $timezone !== null && $timezone !== '' && isset($identifiers[$timezone]);
    }

    /** UTC offset in whole minutes of a valid identifier at $at (DST-aware). */
    public static function offsetMinutes(string $timezone, DateTimeInterface $at): int
    {
        return intdiv((new DateTimeZone($timezone))->getOffset($at), 60);
    }
}

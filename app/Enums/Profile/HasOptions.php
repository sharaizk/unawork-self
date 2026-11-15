<?php

namespace App\Enums\Profile;

/**
 * Shared helpers for profile enums: stable machine values in the database, translated labels in
 * the UI (label() is implemented by each enum).
 */
trait HasOptions
{
    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /** @return array<string, string> value => translated label, in declaration order */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

<?php

namespace App\Services\Matching;

use App\Enums\Matching\MatchCriterion;
use Illuminate\Validation\ValidationException;

/**
 * A worker requirement's mandatory criteria are unknown or not configured (UNA-013). A
 * ValidationException, so a web request redirects back with the message instead of failing.
 */
class InvalidMatchingConfiguration extends ValidationException
{
    /** @param array<int, mixed> $values */
    public static function unknown(string $key, array $values): self
    {
        return static::withMessages([$key => __('Unknown matching criteria: :values.', ['values' => implode(', ', array_map('strval', $values))])]);
    }

    /** @param MatchCriterion[] $criteria */
    public static function notConfigured(string $key, array $criteria): self
    {
        return static::withMessages([$key => __('Only configured criteria can be mandatory. Configure or unselect: :criteria.', [
            'criteria' => implode(', ', array_map(fn (MatchCriterion $c) => $c->label(), $criteria)),
        ])]);
    }
}

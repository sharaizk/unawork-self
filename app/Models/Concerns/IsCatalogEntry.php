<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Administrator-managed reference catalogs (Industries, Skills, Professional Roles / job titles).
 *
 * A model using this trait declares the column holding its label (catalogColumn()) and the
 * Eloquent relations that reference it (usageRelations()). Usage is always counted through those
 * relations, and App\Services\Catalog\CatalogService refuses to delete an entry that is in use.
 */
trait IsCatalogEntry
{
    /** Column holding the entry's label (`name` or `title`). */
    abstract public static function catalogColumn(): string;

    /** @return string[] relations whose records reference this entry */
    abstract public static function usageRelations(): array;

    /** Trim and collapse internal whitespace; capitalization is kept as entered. */
    public static function normalizeLabel(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    public function catalogLabel(): string
    {
        return (string) $this->getAttribute(static::catalogColumn());
    }

    public function scopeAlphabetical(Builder $query): Builder
    {
        return $query->orderBy(static::catalogColumn())->orderBy($this->getKeyName());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = static::normalizeLabel($term);
        if ($term === '') {
            return $query;
        }
        // '!' as the LIKE escape character behaves the same on MySQL and SQLite.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term));

        return $query->whereRaw('LOWER(' . $query->getQuery()->getGrammar()->wrap(static::catalogColumn()) . ") LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
    }

    /** Case-insensitive exact match on the label (after normalization). */
    public function scopeWhereLabel(Builder $query, ?string $label): Builder
    {
        return $query->whereRaw('LOWER(' . $query->getQuery()->getGrammar()->wrap(static::catalogColumn()) . ') = ?', [mb_strtolower(static::normalizeLabel($label))]);
    }

    /** Adds `<relation>_count` for every usage relation. */
    public function scopeWithUsageCounts(Builder $query): Builder
    {
        return $query->withCount(static::usageRelations());
    }

    /**
     * Classification relations (e.g. a skill's industries). They are not usage: deleting the entry
     * just removes these links. Override in the model.
     *
     * @return string[]
     */
    public static function tagRelations(): array
    {
        return [];
    }

    /**
     * Entries tagged with $industryId, plus entries not tied to any industry (general entries).
     * Requires an `industries` relation on the model.
     */
    public function scopeForIndustry(Builder $query, ?int $industryId): Builder
    {
        if (!$industryId) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereHas('industries', fn (Builder $i) => $i->whereKey($industryId))
            ->orWhereDoesntHave('industries'));
    }

    /** Total referencing records (uses loaded counts when present). */
    public function usageCount(): int
    {
        $total = 0;
        foreach (static::usageRelations() as $relation) {
            $attribute = \Illuminate\Support\Str::snake($relation) . '_count';
            if (!array_key_exists($attribute, $this->getAttributes())) {
                $this->loadCount($relation);
            }
            $total += (int) $this->getAttribute($attribute);
        }

        return $total;
    }
}

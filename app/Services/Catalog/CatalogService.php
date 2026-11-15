<?php

namespace App\Services\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes for administrator-managed catalogs (Industry, Skill, JobTitle - see IsCatalogEntry).
 * Labels are normalized before storage; an entry that is referenced anywhere is never deleted, so
 * no foreign-key cascade can silently remove profile skills, preferred roles, experience data,
 * vacancy requirements, POD skills or workspace classifications.
 */
class CatalogService
{
    /**
     * @param class-string<Model> $model
     * @param int[]|null $industryIds industry tags (skills and roles); null leaves them untouched
     */
    public function create(string $model, string $label, ?array $industryIds = null): Model
    {
        return DB::transaction(function () use ($model, $label, $industryIds) {
            $entry = new $model();
            $entry->setAttribute($model::catalogColumn(), $model::normalizeLabel($label));
            $entry->save();
            $this->syncIndustries($entry, $industryIds);

            return $entry;
        });
    }

    /** @param int[]|null $industryIds null leaves the industry tags untouched */
    public function rename(Model $entry, string $label, ?array $industryIds = null): Model
    {
        return DB::transaction(function () use ($entry, $label, $industryIds) {
            $entry->setAttribute($entry::catalogColumn(), $entry::normalizeLabel($label));
            $entry->save();
            $this->syncIndustries($entry, $industryIds);

            return $entry;
        });
    }

    private function syncIndustries(Model $entry, ?array $industryIds): void
    {
        if ($industryIds === null || !in_array('industries', $entry::tagRelations(), true)) {
            return;
        }
        $entry->industries()->sync(array_values(array_unique(array_map('intval', $industryIds))));
        $entry->unsetRelation('industries');
    }

    /**
     * Delete an unused entry. Throws CatalogEntryInUse (and changes nothing) when it is referenced.
     * The row is locked so the usage check and the delete happen together.
     */
    public function delete(Model $entry, string $inUseMessage): void
    {
        DB::transaction(function () use ($entry, $inUseMessage) {
            $locked = $entry->newQuery()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $usage = $locked->usageCount();
            if ($usage > 0) {
                throw new CatalogEntryInUse(__($inUseMessage, ['count' => $usage]), $usage);
            }
            // Classification links (e.g. a skill's industries) are not usage; drop them explicitly.
            foreach ($locked::tagRelations() as $relation) {
                $locked->{$relation}()->detach();
            }
            $locked->delete();
        });
    }
}

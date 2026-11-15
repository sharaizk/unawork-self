<?php

namespace Database\Seeders;

use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reference catalog: the canonical industries plus industry-tagged skills and professional roles
 * (database/seeders/data/catalog.php).
 *
 * Idempotent and non-destructive, so it is safe on a database that already has catalog data:
 * - industries come from the reference-industries migration (same case/whitespace-insensitive
 *   matching), so it never duplicates "HealthCare" vs "Healthcare";
 * - an existing skill or role is reused when its label matches case- and whitespace-insensitively,
 *   or matches one of the dataset's aliases (e.g. the existing "Devops" row is used for
 *   "DevOps Engineer"); existing rows are never renamed or deleted;
 * - industry tags are only added (syncWithoutDetaching), never removed, and `general` entries are
 *   never tagged, so they stay available for every industry.
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\CatalogSeeder
 */
class CatalogSeeder extends Seeder
{
    public const DATA = __DIR__ . '/data/catalog.php';
    public const INDUSTRY_MIGRATION = 'migrations/2026_11_18_000000_install_reference_industries.php';

    /** @var array{industries:int, skills:int, roles:int, tags:int} rows created / links added */
    public array $created = ['industries' => 0, 'skills' => 0, 'roles' => 0, 'tags' => 0];

    public function run(): void
    {
        $data = require self::DATA;

        DB::transaction(function () use ($data) {
            $industryIds = $this->seedIndustries();

            foreach (['skills' => Skill::class, 'roles' => JobTitle::class] as $kind => $model) {
                $general = $data['general'][$kind] ?? [];
                $tags = [];
                foreach ($data['industries'] as $industry => $lists) {
                    $industryId = $industryIds[$this->key($industry)]
                        ?? throw new \RuntimeException("Unknown industry in catalog data: {$industry}");
                    foreach ($lists[$kind] ?? [] as $label) {
                        $tags[$label][] = $industryId;
                    }
                }

                foreach ($general as $label) {
                    $this->findOrCreate($model, $label, $data['aliases'][$label] ?? [], $kind);
                }
                foreach ($tags as $label => $ids) {
                    $entry = $this->findOrCreate($model, $label, $data['aliases'][$label] ?? [], $kind);
                    if (in_array($label, $general, true)) {
                        continue; // general entries stay untagged
                    }
                    $this->created['tags'] += count($entry->industries()->syncWithoutDetaching(array_values(array_unique($ids)))['attached']);
                }
            }
        });

        $this->command?->info(sprintf(
            'Catalog seeded: %d industries, %d skills and %d professional roles created; %d industry links added.',
            $this->created['industries'], $this->created['skills'], $this->created['roles'], $this->created['tags']
        ));
    }

    /** @return array<string, int> normalized lower-case industry name => id */
    private function seedIndustries(): array
    {
        $before = Industry::count();
        (require database_path(self::INDUSTRY_MIGRATION))->up();
        $this->created['industries'] = Industry::count() - $before;

        return Industry::query()->get(['id', 'name'])
            ->mapWithKeys(fn (Industry $industry) => [$this->key($industry->name) => $industry->id])
            ->all();
    }

    /** @param class-string<Model> $model */
    private function findOrCreate(string $model, string $label, array $aliases, string $kind): Model
    {
        foreach (array_merge([$label], $aliases) as $candidate) {
            $existing = $model::query()->whereLabel($candidate)->orderBy('id')->first();
            if ($existing) {
                return $existing;
            }
        }

        $entry = new $model();
        $entry->setAttribute($model::catalogColumn(), $model::normalizeLabel($label));
        $entry->save();
        $this->created[$kind]++;

        return $entry;
    }

    private function key(string $name): string
    {
        return mb_strtolower(Industry::normalizeLabel($name));
    }
}

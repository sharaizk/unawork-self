<?php

namespace Tests\Feature\Catalog;

use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Skill;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * CatalogSeeder: industries, industry-tagged skills and professional roles, reusing existing rows.
 * In-memory SQLite only.
 */
class CatalogSeederTest extends TestCase
{
    use UsesInMemorySqlite;

    /** The professional roles already present in production (legacy JobTitleSeeder). */
    private const EXISTING_ROLES = [
        'Backend Developer', 'Business Analyst', 'Copyrighter', 'Devops', 'Frontend Developer',
        'Product Manager', 'Project Manager', 'QA Engineer', 'UI/UX Engineer',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
    }

    public function test_dataset_is_consistent(): void
    {
        $data = require CatalogSeeder::DATA;
        $canonical = (require database_path(CatalogSeeder::INDUSTRY_MIGRATION))::INDUSTRIES;

        $this->assertSame($canonical, array_keys($data['industries']), 'one entry per canonical industry, same order');
        foreach (['skills', 'roles'] as $kind) {
            $general = array_map('mb_strtolower', $data['general'][$kind]);
            foreach ($data['industries'] as $industry => $lists) {
                $this->assertNotEmpty($lists[$kind], "$industry has $kind");
                $this->assertSame([], array_values(array_intersect(array_map('mb_strtolower', $lists[$kind]), $general)), "$industry repeats general $kind");
                $this->assertSame(count($lists[$kind]), count(array_unique(array_map('mb_strtolower', $lists[$kind]))), "$industry duplicate $kind");
            }
        }
    }

    public function test_seeds_the_catalog_and_reuses_existing_roles_and_skills(): void
    {
        foreach (self::EXISTING_ROLES as $title) {
            JobTitle::create(['title' => $title]);
        }
        $existingRoles = JobTitle::orderBy('id')->pluck('title', 'id')->all();
        $php = Skill::create(['name' => 'php']);                       // different case
        $legacy = Industry::create(['name' => 'HealthCare']);           // legacy spelling
        $custom = Industry::create(['name' => 'Space Mining']);         // custom industry
        $pm = JobTitle::where('title', 'Project Manager')->sole();
        $pm->industries()->attach($custom->id);                          // an admin's own tag

        $seeder = $this->runCatalogSeeder();

        // Existing rows are reused untouched; no near-duplicates via aliases or case.
        $this->assertSame($existingRoles, JobTitle::whereIn('id', array_keys($existingRoles))->orderBy('id')->pluck('title', 'id')->all());
        foreach (['DevOps Engineer', 'Copywriter', 'UI/UX Designer', 'PHP', 'Healthcare'] as $duplicate) {
            $this->assertSame(0, JobTitle::where('title', $duplicate)->count() + Skill::where('name', $duplicate)->count() + Industry::where('name', $duplicate)->count(), $duplicate);
        }
        $this->assertSame('php', $php->fresh()->name);

        // Existing roles are tagged with the matching industries.
        $this->assertTags(JobTitle::class, 'Devops', ['Technology', 'Software & Technology']);
        $this->assertTags(JobTitle::class, 'Copyrighter', ['Digital Media & Publishing', 'Public Relations & Communications', 'Design & Creative Services', 'Marketing & Advertising']);
        $this->assertTags(JobTitle::class, 'UI/UX Engineer', ['Technology', 'Design & Creative Services', 'Software & Technology']);
        $this->assertTags(JobTitle::class, 'Backend Developer', ['Technology', 'Software & Technology']);
        $this->assertTags(JobTitle::class, 'QA Engineer', ['Technology', 'Gaming & Interactive Entertainment', 'Software & Technology']);
        $this->assertTags(Skill::class, 'php', ['Technology', 'Software & Technology']);
        $this->assertTags(Skill::class, 'Triage', ['Healthcare'], 'HealthCare'); // tagged on the legacy row

        // General entries stay untagged (offered for every industry); admin tags are kept.
        $this->assertTags(JobTitle::class, 'Business Analyst', []);
        $this->assertTags(Skill::class, 'Communication', []);
        $this->assertSame([$custom->id], $pm->fresh()->industries->pluck('id')->all());

        // Every industry offers skills and roles; 54 canonical + the custom one.
        $this->assertSame(55, Industry::count());
        $this->assertSame(0, Industry::whereDoesntHave('skills')->where('id', '!=', $custom->id)->count());
        $this->assertSame(0, Industry::whereDoesntHave('jobTitles')->where('id', '!=', $custom->id)->count());
        $this->assertSame(53, $seeder->created['industries']);
        $this->assertSame('HealthCare', $legacy->fresh()->name);
    }

    public function test_running_twice_changes_nothing(): void
    {
        foreach (self::EXISTING_ROLES as $title) {
            JobTitle::create(['title' => $title]);
        }
        $this->runCatalogSeeder();
        $snapshot = $this->snapshot();

        $second = $this->runCatalogSeeder();

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame(['industries' => 0, 'skills' => 0, 'roles' => 0, 'tags' => 0], $second->created);
        $this->assertSame(Skill::count(), Skill::get()->map(fn ($s) => mb_strtolower($s->name))->unique()->count(), 'no case-insensitive duplicates');
        $this->assertSame(JobTitle::count(), JobTitle::get()->map(fn ($r) => mb_strtolower($r->title))->unique()->count());
    }

    // ================================================================ helpers

    private function runCatalogSeeder(): CatalogSeeder
    {
        $seeder = $this->app->make(CatalogSeeder::class)->setContainer($this->app);
        $seeder->__invoke();

        return $seeder;
    }

    private function assertTags(string $model, string $label, array $industries, ?string $as = null): void
    {
        $entry = $model::query()->whereLabel($label)->sole();
        $names = $entry->industries()->pluck('name')->all();
        $expected = $as ? array_map(fn ($n) => $n === 'Healthcare' ? $as : $n, $industries) : $industries;
        $this->assertEqualsCanonicalizing($expected, $names, $label);
    }

    private function snapshot(): array
    {
        return collect(['industries', 'skills', 'job_titles', 'industry_skill', 'industry_job_title'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])->all();
    }
}

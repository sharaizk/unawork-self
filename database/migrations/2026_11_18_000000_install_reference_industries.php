<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Production reference data: the canonical industry catalog. Deploys run `migrate` but never
 * `db:seed`, so this ships as an additive, idempotent data migration.
 *
 * up(): inserts each canonical industry that is missing, comparing case- and whitespace-
 * insensitively, so an existing "HealthCare" or " Banking " counts as present. Existing rows,
 * custom industries, ids and workspace links are never changed. Safe to run again.
 *
 * Spelling normalized from the supplied list: HealthCare -> Healthcare,
 * Gamin & Interactive Entertainment -> Gaming & Interactive Entertainment,
 * Hospitality & Accomodations -> Hospitality & Accommodations.
 *
 * down(): removes only rows this migration inserted (identified by the INSTALLED_AT timestamp in
 * both created_at and updated_at) that no workspace references and nobody renamed. Industries that
 * existed before, were renamed, or are used by a workspace are intentionally preserved.
 */
return new class extends Migration
{
    public const INSTALLED_AT = '2026-11-18 00:00:00';

    public const INDUSTRIES = [
        'Construction',
        'Healthcare',
        'Finance',
        'Education',
        'Government & Public Administration',
        'Legal Services',
        'Technology',
        'Automotive',
        'Banking',
        'Manufacturing',
        'Real Estate',
        'Community & Social Services',
        'Commercial & Retail',
        'Energy',
        'Food & Beverage Manufacturing',
        'Travel & Tourism',
        'Telecommunications',
        'Architecture & Urban Planning',
        'Arts, Culture & Entertainment',
        'Biotechnology & Life Sciences',
        'Consumer Goods',
        'Cybersecurity',
        'Digital Media & Publishing',
        'E-Commerce & Online Retail',
        'Environmental Services & Sustainability',
        'Fashion & Apparel',
        'Film, Television & Broadcasting',
        'Food & Beverage',
        'Forestry & Natural Resources',
        'Gaming & Interactive Entertainment',
        'Hospitality & Accommodations',
        'Human Resources & Staffing',
        'Insurance',
        'International Trade & Commerce',
        'Logistics & Supply Chain',
        'Nonprofit & Philanthropy',
        'Pharmaceuticals',
        'Professional & Business Services',
        'Public Relations & Communications',
        'Renewable Energy',
        'Research & Development',
        'Restaurants & Food Services',
        'Robotics & Automation',
        'Science & Scientific Research',
        'Sports & Recreation',
        'Venture Capital & Private Equity',
        'Web3, Blockchain & Digital Assets',
        'Design & Creative Services',
        'Software & Technology',
        'Marketing & Advertising',
        'Sales & Business Development',
        'Artificial Intelligence & Machine Learning',
        'Mental & Behavioral Health',
        'Childcare & Early Childhood Services',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $existing = DB::table('industries')->lockForUpdate()->pluck('name')
                ->map(fn ($name) => $this->key($name))->flip();

            $rows = [];
            foreach (self::INDUSTRIES as $name) {
                if (!$existing->has($this->key($name))) {
                    $rows[] = ['name' => $name, 'created_at' => self::INSTALLED_AT, 'updated_at' => self::INSTALLED_AT];
                    $existing->put($this->key($name), true);
                }
            }

            if ($rows) {
                DB::table('industries')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        $query = DB::table('industries')
            ->whereIn('name', self::INDUSTRIES)
            ->where('created_at', self::INSTALLED_AT)
            ->where('updated_at', self::INSTALLED_AT);

        if (\Illuminate\Support\Facades\Schema::hasColumn('workspaces', 'industry_id')) {
            $query->whereNotExists(function ($used) {
                $used->selectRaw('1')->from('workspaces')->whereColumn('workspaces.industry_id', 'industries.id');
            });
        }

        $query->delete();
    }

    private function key(?string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $name)));
    }
};

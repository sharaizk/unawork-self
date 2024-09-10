<?php

namespace Database\Seeders;

use App\Models\Industry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IndustrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $industries = [
            'E-commerce',
            'Healthcare',
            'Education',
            'Travel',
            'Construction',
            'Real Estate',
            'Hospitality',
            'IT'
        ];

        foreach ($industries as $industry) {
            Industry::firstOrCreate(['name' => $industry]);
        }
    }
}

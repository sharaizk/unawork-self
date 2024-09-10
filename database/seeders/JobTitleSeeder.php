<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $titles = [
            'Backend Developer',
            'Frontend Developer',
            'UI/UX Engineer',
            'Business Analyst',
            'Copyrighter',
            'Devops',
            'QA Engineer',
            'Project Manager',
            'Product Manager'
        ];

        foreach ($titles as $title) {
            JobTitle::firstOrCreate([
                'title' => $title
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    // Confirm exact five levels and labels against the NEP Programme Mapping
    // Framework v1.2 — these are a reasonable placeholder set, not confirmed.
    public function run(): void
    {
        $levels = [
            'Pre-school',
            'Primary',
            'Lower Secondary',
            'Upper Secondary',
            'Higher Education / TVET',
        ];

        foreach ($levels as $name) {
            EducationLevel::firstOrCreate(['level_name' => $name]);
        }
    }
}

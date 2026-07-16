<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Organisation;
use App\Models\Province;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\BudgetBand;
use App\Models\Taxonomy;

class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $organisations = Organisation::all();
        $provinces = Province::all();
        $districts = District::all();
        $educationLevels = EducationLevel::all();
        $budgetBands = BudgetBand::all();
        $taxonomies = Taxonomy::all();

        $programmeNames = [
            'Rural Literacy Programme',
            'Girls in STEM Initiative',
            'Community Health Education',
            'Vocational Skills Training',
            'Early Childhood Development',
            'Teacher Capacity Building',
        ];

        $statuses = ['planned', 'active', 'completed'];

        $rows = [];

        foreach ($organisations as $i => $organisation) {
            // give each organisation 2 programmes for realistic variety
            for ($j = 0; $j < 2; $j++) {
                $index = ($i * 2 + $j) % count($programmeNames);
                $status = $statuses[$index % count($statuses)];

                $startDate = match ($status) {
                    'completed' => now()->subMonths(8),
                    'active' => now()->subMonths(2),
                    default => now()->addMonth(),
                };

                $endDate = match ($status) {
                    'completed' => now()->subMonths(1),
                    'active' => now()->addMonths(4),
                    default => now()->addMonths(6),
                };

                $rows[] = [
                    'organisation_id' => $organisation->id,
                    'province_id' => $provinces->isNotEmpty() ? $provinces->random()->id : null,
                    'district_id' => $districts->isNotEmpty() ? $districts->random()->id : null,
                    'education_level_id' => $educationLevels->isNotEmpty() ? $educationLevels->random()->id : null,
                    'budget_band_id' => $budgetBands->isNotEmpty() ? $budgetBands->random()->id : null,
                    'taxonomy_id' => $taxonomies->isNotEmpty() ? $taxonomies->random()->id : null,
                    'name' => $programmeNames[$index] . ' - ' . $organisation->name,
                    'description' => 'A programme run by ' . $organisation->name . ' focused on ' . strtolower($programmeNames[$index]) . '.',
                    'budget_amount' => rand(10, 100) * 1000,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('programmes')->insert($rows);
    }
}
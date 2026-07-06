<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,
            EducationLevelSeeder::class,
            BudgetBandSeeder::class,
            TaxonomySeeder::class,
        ]);

        // Districts intentionally left unseeded here — Cambodia has 200+
        // districts across 25 provinces; pull the controlled list from an
        // authoritative source (NEP's existing CRM data, if available) rather
        // than hand-typing it here.
    }
}

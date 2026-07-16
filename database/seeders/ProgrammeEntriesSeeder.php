<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgrammeEntriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('programme_entries')->insert([
            [
                'organisation_id' => 1,
                'budget_band_id' => 3,
                'programme_name' => 'June',
                'start_year' => 2026,
                'end_year' => null,
                'ongoing' => 1,
                'fte_staff' => 12,
                'indirect_beneficiaries' => 23,
                'direct_beneficiaries' => 12,
                'method' => null,
                'verified_date' => null,
                'last_updated_by' => null,
                'is_unverified' => 0,
                'is_submitted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => 2,
                'budget_band_id' => 1,
                'programme_name' => 'Music',
                'start_year' => 2026,
                'end_year' => null,
                'ongoing' => 1,
                'fte_staff' => 12,
                'indirect_beneficiaries' => 12,
                'direct_beneficiaries' => 12,
                'method' => null,
                'verified_date' => null,
                'last_updated_by' => null,
                'is_unverified' => 0,
                'is_submitted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], 
            [
                'organisation_id' => 3,
                'budget_band_id' => 1,
                'programme_name' => 'Education',
                'start_year' => 2026,
                'end_year' => null,
                'ongoing' => 1,
                'fte_staff' => 12,
                'indirect_beneficiaries' => 12,
                'direct_beneficiaries' => 12,
                'method' => null,
                'verified_date' => null,
                'last_updated_by' => null,
                'is_unverified' => 0,
                'is_submitted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => 1,
                'budget_band_id' => 1,
                'programme_name' => 'Art and Culture',
                'start_year' => 2026,
                'end_year' => null,
                'ongoing' => 1,
                'fte_staff' => 12,
                'indirect_beneficiaries' => 12,
                'direct_beneficiaries' => 12,
                'method' => null,
                'verified_date' => null,
                'last_updated_by' => null,
                'is_unverified' => 0,
                'is_submitted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
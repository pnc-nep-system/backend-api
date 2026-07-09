<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganisationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('organisations')->insert([
            [
                'name' => 'ABC Organisation',
                'contact_name' => 'John Doe',
                'email' => 'contact@abc.org',
                'member_since' => 2024,
                'status' => 'active',
                'last_inactive_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'XYZ Foundation',
                'contact_name' => 'Jane Smith',
                'email' => 'info@xyz.org',
                'member_since' => 2024,
                'status' => 'active',
                'last_inactive_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Helping Hands',
                'contact_name' => 'David Lee',
                'email' => 'support@helpinghands.org',
                'member_since' => 2024,
                'status' => 'inactive',
                'last_inactive_at' => now()->subDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
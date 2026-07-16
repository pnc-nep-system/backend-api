<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Organisation;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch all organisations ordered by id, so we can reference them by position
        $organisations = Organisation::orderBy('id')->get();

        DB::table('users')->insert([
            [
                'organisation_id' => null,
                'name' => 'System Admin',
                'email' => 'admin@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'nep_admin',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => null,
                'name' => 'Organisation Coordinator',
                'email' => 'coordinator@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'nep_coordinator',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $organisations[0]->id, // 1st organisation
                'name' => 'Organisation Admin',
                'email' => 'orgadmin@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $organisations[1]->id, // 2nd organisation
                'name' => 'PNC Organisation',
                'email' => 'pnc@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $organisations[2]->id, // 3rd organisation
                'name' => 'PSE Organisation',
                'email' => 'pse@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
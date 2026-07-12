<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
                'organisation_id' => \App\Models\Organisation::first()->id,
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
        ]);
    }
}

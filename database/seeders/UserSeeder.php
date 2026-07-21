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
        $organisations = Organisation::orderBy('id')->get();
        $ddspOrg = Organisation::where('name', 'like', '%DDSP%')->first() ?? $organisations->first();

        $users = [
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
                'organisation_id' => $ddspOrg->id ?? null,
                'name' => 'DDSP Main Admin',
                'email' => 'ddsp@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $ddspOrg->id ?? null,
                'name' => 'DDSP ACFC Lead',
                'email' => 'ddsp.acfc@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $ddspOrg->id ?? null,
                'name' => 'DDSP SBCF Doc 1 Lead',
                'email' => 'ddsp.sbcf1@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $ddspOrg->id ?? null,
                'name' => 'DDSP SBCF Doc 2 Lead',
                'email' => 'ddsp.sbcf2@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $ddspOrg->id ?? null,
                'name' => 'DDSP MKP Lead',
                'email' => 'ddsp.mkp@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $ddspOrg->id ?? null,
                'name' => 'DDSP PQR Lead',
                'email' => 'ddsp.pqr@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member_org',
                'status' => 'active',
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organisation_id' => $organisations[1]->id ?? null,
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
                'organisation_id' => $organisations[2]->id ?? null,
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
                'organisation_id' => $organisations[3]->id ?? null,
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
        ];

        foreach ($users as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                $u
            );
        }
    }
}
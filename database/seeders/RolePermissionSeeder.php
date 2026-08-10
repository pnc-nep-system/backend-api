<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // User management
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'Users', 'description' => 'View user accounts'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'Users', 'description' => 'Create new user accounts'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'group' => 'Users', 'description' => 'Update user accounts'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'Users', 'description' => 'Delete user accounts'],
            ['name' => 'users.assign-roles', 'display_name' => 'Assign Roles', 'group' => 'Users', 'description' => 'Assign roles to users'],
            
            // Organisation management
            ['name' => 'organisations.view', 'display_name' => 'View Organisations', 'group' => 'Organisations', 'description' => 'View organisations'],
            ['name' => 'organisations.create', 'display_name' => 'Create Organisations', 'group' => 'Organisations', 'description' => 'Create new organisations'],
            ['name' => 'organisations.update', 'display_name' => 'Update Organisations', 'group' => 'Organisations', 'description' => 'Update organisations'],
            ['name' => 'organisations.delete', 'display_name' => 'Delete Organisations', 'group' => 'Organisations', 'description' => 'Delete organisations'],
            
            // Programme management
            ['name' => 'programmes.view', 'display_name' => 'View Programmes', 'group' => 'Programmes', 'description' => 'View programme entries'],
            ['name' => 'programmes.create', 'display_name' => 'Create Programmes', 'group' => 'Programmes', 'description' => 'Create programme entries'],
            ['name' => 'programmes.update', 'display_name' => 'Update Programmes', 'group' => 'Programmes', 'description' => 'Update programme entries'],
            ['name' => 'programmes.verify', 'display_name' => 'Verify Programmes', 'group' => 'Programmes', 'description' => 'Verify programme entries'],
            
            // Advisory management
            ['name' => 'advisory.view', 'display_name' => 'View Advisory', 'group' => 'Advisory', 'description' => 'View advisory submissions'],
            ['name' => 'advisory.create', 'display_name' => 'Create Advisory', 'group' => 'Advisory', 'description' => 'Create advisory submissions'],
            ['name' => 'advisory.update', 'display_name' => 'Update Advisory', 'group' => 'Advisory', 'description' => 'Update advisory submissions'],
            ['name' => 'advisory.deliver', 'display_name' => 'Deliver Advisory', 'group' => 'Advisory', 'description' => 'Deliver advisory notes'],
            
            // Role management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'Roles', 'description' => 'View roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'Roles', 'description' => 'Create new roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'group' => 'Roles', 'description' => 'Update roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'Roles', 'description' => 'Delete roles'],
            ['name' => 'roles.assign', 'display_name' => 'Assign Roles', 'group' => 'Roles', 'description' => 'Assign roles to users'],
            
            // Permission management
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'Permissions', 'description' => 'View permissions'],
            ['name' => 'permissions.create', 'display_name' => 'Create Permissions', 'group' => 'Permissions', 'description' => 'Create new permissions'],
            ['name' => 'permissions.update', 'display_name' => 'Update Permissions', 'group' => 'Permissions', 'description' => 'Update permissions'],
            ['name' => 'permissions.delete', 'display_name' => 'Delete Permissions', 'group' => 'Permissions', 'description' => 'Delete permissions'],
            
            // Taxonomy management
            ['name' => 'taxonomy.view', 'display_name' => 'View Taxonomy', 'group' => 'Taxonomy', 'description' => 'View taxonomy'],
            ['name' => 'taxonomy.create', 'display_name' => 'Create Taxonomy', 'group' => 'Taxonomy', 'description' => 'Create taxonomy items'],
            ['name' => 'taxonomy.update', 'display_name' => 'Update Taxonomy', 'group' => 'Taxonomy', 'description' => 'Update taxonomy items'],
            ['name' => 'taxonomy.delete', 'display_name' => 'Delete Taxonomy', 'group' => 'Taxonomy', 'description' => 'Delete taxonomy items'],
            
            // Dashboard and reports
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'group' => 'Dashboard', 'description' => 'View dashboard and statistics'],
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'group' => 'Reports', 'description' => 'View reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'group' => 'Reports', 'description' => 'Export reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrInsert(['name' => $permission['name']], $permission);
        }

        // Create roles
        $adminRole = Role::updateOrInsert(
            ['name' => 'nep_admin'],
            [
                'display_name' => 'NEP Administrator',
                'description' => 'Full system access with all permissions',
                'is_system' => true,
            ]
        );

        $coordinatorRole = Role::updateOrInsert(
            ['name' => 'nep_coordinator'],
            [
                'display_name' => 'NEP Coordinator',
                'description' => 'Organisation-level access with limited administrative functions',
                'is_system' => true,
            ]
        );

        $memberRole = Role::updateOrInsert(
            ['name' => 'member_org'],
            [
                'display_name' => 'Member Organisation',
                'description' => 'Basic access to assigned programmes only',
                'is_system' => true,
            ]
        );

        // Assign all permissions to admin
        $admin = Role::where('name', 'nep_admin')->first();
        if ($admin) {
            $admin->permissions()->sync(Permission::all()->pluck('id'));
        }

        // Assign specific permissions to coordinator
        $coordinator = Role::where('name', 'nep_coordinator')->first();
        if ($coordinator) {
            $coordinatorPermissions = [
                'users.view',
                'organisations.view',
                'programmes.view',
                'programmes.create',
                'programmes.update',
                'advisory.view',
                'advisory.create',
                'advisory.update',
                'dashboard.view',
                'reports.view',
                'reports.export',
                'taxonomy.view',
            ];
            $coordinator->permissions()->sync(
                Permission::whereIn('name', $coordinatorPermissions)->pluck('id')
            );
        }

        // Assign specific permissions to member
        $member = Role::where('name', 'member_org')->first();
        if ($member) {
            $memberPermissions = [
                'programmes.view',
                'programmes.create',
                'programmes.update',
                'advisory.view',
            ];
            $member->permissions()->sync(
                Permission::whereIn('name', $memberPermissions)->pluck('id')
            );
        }

        // Backfill and repair the role relation for users created before RBAC existed.
        User::query()->select(['id', 'role'])->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $user->syncLegacyRole();
            }
        });
    }
}

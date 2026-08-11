<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $coordinatorUser;
    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the real production seeder rather than hand-rolled fixtures so
        // these tests stay in sync with the actual permission taxonomy.
        $this->seed(RolePermissionSeeder::class);

        // Creating these via Eloquent triggers User::booted()'s role_user sync,
        // so no manual ->roles()->attach() is needed.
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_admin',
            'status' => 'active',
        ]);

        $this->coordinatorUser = User::create([
            'name' => 'Coordinator User',
            'email' => 'coordinator@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_coordinator',
            'status' => 'active',
        ]);

        $this->memberUser = User::create([
            'name' => 'Member User',
            'email' => 'member@test.com',
            'password' => bcrypt('password'),
            'role' => 'member_org',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_view_all_users(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'status', 'organisation_id']
                ]
            ]);
    }

    public function test_admin_can_view_single_user_with_roles_and_effective_permissions(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson("/api/admin/users/{$this->coordinatorUser->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email', 'roles', 'effective_permissions'])
            ->assertJsonFragment(['name' => 'nep_coordinator']);
    }

    public function test_coordinator_can_view_but_not_create_users(): void
    {
        // Coordinator holds users.view (RolePermissionSeeder) but not
        // users.create — read-only access to the user list, matching the
        // permission the seeder has always granted them.
        $this->actingAs($this->coordinatorUser, 'sanctum');

        $this->getJson('/api/admin/users')->assertStatus(200);

        $this->postJson('/api/admin/users', [
            'name' => 'New User', 'email' => 'blocked@test.com', 'role' => 'member_org',
        ])->assertStatus(403);
    }

    public function test_member_cannot_view_users(): void
    {
        $this->actingAs($this->memberUser, 'sanctum');

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'role' => 'member_org',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role']
            ]);
    }

    public function test_admin_can_assign_role_to_user(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $role = Role::where('name', 'nep_coordinator')->first();

        $response = $this->postJson("/api/admin/roles/{$role->id}/users/{$this->memberUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Role assigned to user successfully.'
            ]);

        $this->assertTrue($this->memberUser->roles()->where('roles.id', $role->id)->exists());
    }

    public function test_admin_can_view_roles(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'display_name', 'permissions']
            ]);
    }

    public function test_coordinator_without_roles_permission_cannot_view_roles(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(403);
    }

    public function test_admin_can_manage_permissions(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        // View permissions
        $response = $this->getJson('/api/admin/permissions');
        $response->assertStatus(200);

        // Create permission
        $response = $this->postJson('/api/admin/permissions', [
            'name' => 'test.permission',
            'display_name' => 'Test Permission',
            'group' => 'Testing',
        ]);
        $response->assertStatus(201);

        // Update permission
        $permission = Permission::where('name', 'test.permission')->first();
        $response = $this->patchJson("/api/admin/permissions/{$permission->id}", [
            'display_name' => 'Updated Test Permission',
        ]);
        $response->assertStatus(200);

        // Delete permission
        $response = $this->deleteJson("/api/admin/permissions/{$permission->id}");
        $response->assertStatus(200);
    }

    public function test_member_cannot_manage_permissions(): void
    {
        $this->actingAs($this->memberUser, 'sanctum');

        $this->getJson('/api/admin/permissions')->assertStatus(403);
        $this->postJson('/api/admin/permissions', [
            'name' => 'test.permission',
            'display_name' => 'Test Permission',
            'group' => 'Testing',
        ])->assertStatus(403);
    }

    public function test_user_has_permission_method_works(): void
    {
        // Admin should have all permissions
        $this->assertTrue($this->adminUser->hasPermission('users.view'));
        $this->assertTrue($this->adminUser->hasPermission('users.create'));
        $this->assertTrue($this->adminUser->hasPermission('organisations.create'));

        // Coordinator should have limited permissions
        $this->assertTrue($this->coordinatorUser->hasPermission('organisations.view'));
        $this->assertFalse($this->coordinatorUser->hasPermission('users.create'));
        $this->assertFalse($this->coordinatorUser->hasPermission('organisations.create'));

        // Member should have minimal permissions
        $this->assertTrue($this->memberUser->hasPermission('programmes.view'));
        $this->assertFalse($this->memberUser->hasPermission('users.view'));
    }

    public function test_user_has_role_method_works(): void
    {
        $this->assertTrue($this->adminUser->hasRole('nep_admin'));
        $this->assertTrue($this->coordinatorUser->hasRole('nep_coordinator'));
        $this->assertTrue($this->memberUser->hasRole('member_org'));

        $this->assertFalse($this->adminUser->hasRole('nep_coordinator'));
    }

    public function test_effective_permissions_are_correctly_calculated(): void
    {
        $permissions = $this->coordinatorUser->effectivePermissions();

        $this->assertTrue($permissions->contains('organisations.view'));
        $this->assertFalse($permissions->contains('users.create'));
    }

    public function test_role_change_updates_effective_permissions(): void
    {
        // Promote the member to coordinator and confirm their granted
        // permission set follows the new role, not the old one.
        $this->memberUser->update(['role' => 'nep_coordinator']);
        $this->memberUser->refresh();

        $this->assertTrue($this->memberUser->hasRole('nep_coordinator'));
        $this->assertFalse($this->memberUser->hasRole('member_org'));
        $this->assertTrue($this->memberUser->hasPermission('organisations.view'));
    }

    public function test_unauthorized_actions_are_blocked(): void
    {
        // Test that member cannot access admin routes
        $this->actingAs($this->memberUser, 'sanctum');

        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->getJson('/api/admin/roles')->assertStatus(403);
        $this->getJson('/api/admin/permissions')->assertStatus(403);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $adminRole = Role::where('name', 'nep_admin')->first();

        $response = $this->deleteJson("/api/admin/roles/{$adminRole->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'System roles cannot be deleted.'
            ]);
    }

    public function test_system_roles_cannot_be_modified(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $adminRole = Role::where('name', 'nep_admin')->first();

        $response = $this->patchJson("/api/admin/roles/{$adminRole->id}", [
            'display_name' => 'Modified Admin',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'System roles cannot be modified.'
            ]);
    }

    public function test_coordinator_can_view_organisations(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');

        $response = $this->getJson('/api/admin/organisations');

        $response->assertStatus(200);
    }

    public function test_member_cannot_create_organisations(): void
    {
        $this->actingAs($this->memberUser, 'sanctum');

        $response = $this->postJson('/api/admin/organisations', [
            'name' => 'Test Organisation',
        ]);

        $response->assertStatus(403);
    }

    public function test_role_permissions_are_enforced_by_backend(): void
    {
        // Test that backend enforces permissions even if frontend allows
        $this->actingAs($this->memberUser, 'sanctum');

        // Member should not be able to create users even if they try
        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'role' => 'member_org',
        ]);

        $response->assertStatus(403);
    }

    // ==================== Super Admin / last-admin safety rails ====================

    public function test_cannot_deactivate_self_as_the_last_active_admin(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson("/api/admin/users/{$this->adminUser->id}/deactivate");

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot deactivate your own account.']);
        $this->assertTrue($this->adminUser->fresh()->isActive());
    }

    public function test_is_last_active_admin_is_computed_correctly(): void
    {
        $this->assertTrue($this->adminUser->isLastActiveAdmin());

        $secondAdmin = User::create([
            'name' => 'Second Admin',
            'email' => 'admin2@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_admin',
            'status' => 'active',
        ]);

        $this->assertFalse($this->adminUser->fresh()->isLastActiveAdmin());
        $this->assertFalse($secondAdmin->isLastActiveAdmin());
        $this->assertFalse($this->coordinatorUser->isLastActiveAdmin());
    }

    public function test_cannot_change_own_role_at_all(): void
    {
        // Self-service role changes are blocked outright (checked before the
        // last-admin guard below, hence the more specific message here).
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->patchJson("/api/admin/users/{$this->adminUser->id}", [
            'role' => 'member_org',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot change your own role.']);

        $this->assertTrue($this->adminUser->fresh()->isNepAdmin());
    }

    public function test_cannot_deactivate_last_active_admin_via_update_endpoint(): void
    {
        // Self-deactivation through the generic update() endpoint (as opposed
        // to the dedicated deactivate() endpoint, covered elsewhere) — the
        // last-active-admin guard is the one that catches this, since it's a
        // status-only payload with no role key.
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->patchJson("/api/admin/users/{$this->adminUser->id}", [
            'status' => 'inactive',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot change the role or status of the last active NEP Administrator.',
            ]);

        $this->assertTrue($this->adminUser->fresh()->isActive());
    }

    public function test_can_demote_an_admin_when_another_active_admin_exists(): void
    {
        $secondAdmin = User::create([
            'name' => 'Second Admin',
            'email' => 'admin2@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_admin',
            'status' => 'active',
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->patchJson("/api/admin/users/{$secondAdmin->id}", [
            'role' => 'nep_coordinator',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($secondAdmin->fresh()->hasRole('nep_coordinator'));
        $this->assertFalse($secondAdmin->fresh()->hasRole('nep_admin'));
    }

    public function test_cannot_remove_nep_admin_role_from_last_active_admin(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $adminRole = Role::where('name', 'nep_admin')->first();

        $response = $this->deleteJson("/api/admin/roles/{$adminRole->id}/users/{$this->adminUser->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot remove the nep_admin role from the last active NEP Administrator.',
            ]);
    }

    public function test_cannot_assign_nonexistent_role_to_user(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson("/api/admin/roles/999999/users/{$this->memberUser->id}");

        $response->assertStatus(404);
    }

    public function test_cannot_create_role_with_invalid_permission_ids(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'custom_role',
            'display_name' => 'Custom Role',
            'permissions' => [999999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_admin_cannot_assign_duplicate_role_name(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'nep_admin',
            'display_name' => 'Duplicate Admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_custom_role_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $viewUsersPermission = Permission::where('name', 'users.view')->first();

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'user_manager',
            'display_name' => 'User Manager',
            'description' => 'Can only view users',
            'permissions' => [$viewUsersPermission->id],
        ]);
        $response->assertStatus(201);
        $roleId = $response->json('id');

        $response = $this->patchJson("/api/admin/roles/{$roleId}", [
            'display_name' => 'User Manager (Updated)',
        ]);
        $response->assertStatus(200)
            ->assertJsonFragment(['display_name' => 'User Manager (Updated)']);

        $response = $this->deleteJson("/api/admin/roles/{$roleId}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }
}

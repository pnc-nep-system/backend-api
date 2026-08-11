<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the dynamic, permission-driven authorization surface added on top of
 * the base RBAC system: privilege-escalation guards, the /user + /session +
 * login "who am I" payload, and the newly permission-gated Policy/Map/Adviser
 * workspace routes.
 */
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $coordinatorUser;
    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::create([
            'name' => 'Admin', 'email' => 'admin@escalation.test',
            'password' => bcrypt('password'), 'role' => 'nep_admin', 'status' => 'active',
        ]);
        $this->coordinatorUser = User::create([
            'name' => 'Coordinator', 'email' => 'coordinator@escalation.test',
            'password' => bcrypt('password'), 'role' => 'nep_coordinator', 'status' => 'active',
        ]);
        $this->memberUser = User::create([
            'name' => 'Member', 'email' => 'member@escalation.test',
            'password' => bcrypt('password'), 'role' => 'member_org', 'status' => 'active',
        ]);
    }

    // ==================== /user, /session, login payload ====================

    public function test_login_response_includes_roles_and_effective_permissions(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@escalation.test',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'role', 'roles', 'permissions'], 'token']);

        $permissions = $response->json('user.permissions');
        $this->assertContains('users.view', $permissions);
        $this->assertContains('roles.delete', $permissions);

        $roles = $response->json('user.roles');
        $this->assertSame('nep_admin', $roles[0]['name']);
    }

    public function test_user_and_session_endpoints_include_permissions(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');

        foreach (['/api/user', '/api/session'] as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(200);
            $permissions = $endpoint === '/api/user' ? $response->json('permissions') : $response->json('user.permissions');
            $this->assertContains('advisory.manage', $permissions);
            $this->assertNotContains('users.create', $permissions);
        }
    }

    // ==================== Privilege escalation guards ====================

    /** A custom role with only users.create/users.view — deliberately weaker than nep_admin. */
    private function makeLimitedUserManagerRole(): Role
    {
        $role = Role::create(['name' => 'user_manager_test', 'display_name' => 'User Manager', 'is_system' => false]);
        $role->permissions()->sync(
            Permission::whereIn('name', ['users.view', 'users.create'])->pluck('id')
        );

        return $role;
    }

    public function test_limited_user_manager_cannot_create_a_nep_admin_account(): void
    {
        $limitedRole = $this->makeLimitedUserManagerRole();
        $actor = User::create([
            'name' => 'Limited Actor', 'email' => 'limited@escalation.test',
            'password' => bcrypt('password'), 'role' => 'member_org', 'status' => 'active',
        ]);
        // Grant only the custom role's permissions (not member_org's), by
        // detaching the auto-synced member_org role and attaching the custom one.
        $actor->roles()->sync([$limitedRole->id]);

        $this->actingAs($actor, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@escalation.test',
            'role' => 'nep_admin',
        ]);

        // Blocked by the route's users.create permission gate first (the actor
        // only holds it via the custom role, so this should actually reach the
        // controller) — the escalation guard inside store() then rejects it
        // because nep_admin carries permissions the actor doesn't have.
        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@escalation.test']);
    }

    public function test_guard_is_strict_even_for_a_seemingly_lesser_role(): void
    {
        // Demonstrates the guard is strict, not "role hierarchy" based: even
        // though member_org sounds "lesser" than an admin account, granting it
        // still requires holding programmes.*/advisory.view/taxonomy.view/
        // policy.view yourself — an actor with only users.view/users.create
        // does not, so this is correctly rejected too.
        $limitedRole = $this->makeLimitedUserManagerRole();
        $actor = User::create([
            'name' => 'Limited Actor 2', 'email' => 'limited2@escalation.test',
            'password' => bcrypt('password'), 'role' => 'member_org', 'status' => 'active',
        ]);
        $actor->roles()->sync([$limitedRole->id]);

        $this->actingAs($actor, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New Member',
            'email' => 'newmember@escalation.test',
            'role' => 'member_org',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_grant_any_role_including_nep_admin(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New Admin',
            'email' => 'newadmin@escalation.test',
            'role' => 'nep_admin',
        ]);

        $response->assertStatus(201);
    }

    public function test_user_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->patchJson("/api/admin/users/{$this->adminUser->id}", [
            'role' => 'nep_coordinator',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot change your own role.']);
    }

    public function test_cannot_assign_role_via_role_management_that_grants_more_than_actor_holds(): void
    {
        $limitedRole = $this->makeLimitedUserManagerRole();
        $actor = User::create([
            'name' => 'Limited Actor 3', 'email' => 'limited3@escalation.test',
            'password' => bcrypt('password'), 'role' => 'member_org', 'status' => 'active',
        ]);
        $actor->roles()->sync([$limitedRole->id]);

        $this->actingAs($actor, 'sanctum');

        $adminRole = Role::where('name', 'nep_admin')->first();

        // Requires roles.assign, which this actor doesn't hold either, so the
        // route-level permission gate rejects it before the controller guard
        // would even run — still a 403 either way.
        $response = $this->postJson("/api/admin/roles/{$adminRole->id}/users/{$this->memberUser->id}");
        $response->assertStatus(403);
    }

    public function test_cannot_create_role_with_permissions_actor_does_not_have(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');

        // Coordinator holds roles.* only if granted — by default it does not,
        // so this is blocked by the route gate. Grant roles.create directly to
        // prove the *controller-level* escalation guard (not just the route
        // gate) independently rejects over-broad permission sets.
        $rolesCreate = Permission::where('name', 'roles.create')->first();
        $coordinatorRole = Role::where('name', 'nep_coordinator')->first();
        $coordinatorRole->permissions()->syncWithoutDetaching([$rolesCreate->id]);

        $usersDelete = Permission::where('name', 'users.delete')->first(); // coordinator does NOT hold this

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'coordinator_minted_role',
            'display_name' => 'Coordinator Minted Role',
            'permissions' => [$usersDelete->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('roles', ['name' => 'coordinator_minted_role']);
    }

    // ==================== Newly permission-gated modules ====================

    public function test_policy_documents_permission_matrix(): void
    {
        $payload = [
            'title' => 'Test Policy', 'document_type' => 'policy',
            'authority' => 'Test Authority', 'version' => '1.0', 'date' => '2024-01-01',
        ];

        // member_org: view only
        $this->actingAs($this->memberUser, 'sanctum');
        $this->getJson('/api/policy-documents')->assertStatus(200);
        $this->postJson('/api/policy-documents', $payload)->assertStatus(403);

        // nep_coordinator: view + create
        $this->actingAs($this->coordinatorUser, 'sanctum');
        $this->postJson('/api/policy-documents', $payload)->assertStatus(201);
    }

    public function test_map_permission_matrix(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');
        $this->getJson('/api/map/entries')->assertStatus(200);

        $this->actingAs($this->memberUser, 'sanctum');
        $this->getJson('/api/map/entries')->assertStatus(403);
    }

    public function test_adviser_workspace_vs_narrow_advisory_view_do_not_leak(): void
    {
        // member_org holds advisory.view (narrow) but NOT advisory.manage — must
        // not be able to reach the staff Adviser workspace list.
        $this->actingAs($this->memberUser, 'sanctum');
        $this->getJson('/api/adviser/submissions')->assertStatus(403);

        $this->actingAs($this->coordinatorUser, 'sanctum');
        $this->getJson('/api/adviser/submissions')->assertStatus(200);
    }

    // ==================== Dynamic role selection (Create/Edit User) ====================

    public function test_admin_can_create_a_user_with_a_custom_role(): void
    {
        $custom = Role::create(['name' => 'accountant', 'display_name' => 'Accountant', 'is_system' => false]);
        $custom->permissions()->sync(Permission::whereIn('name', ['programmes.view'])->pluck('id'));

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New Accountant',
            'email' => 'accountant@escalation.test',
            'role' => 'accountant',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'accountant@escalation.test')->first();
        $this->assertSame('accountant', $user->role);
        $this->assertTrue($user->hasRole('accountant'));
        $this->assertTrue($user->hasPermission('programmes.view'));
    }

    public function test_unknown_role_name_is_rejected(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Nobody',
            'email' => 'nobody@escalation.test',
            'role' => 'role_that_does_not_exist',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['role']);
    }

    public function test_switching_between_two_custom_roles_swaps_permissions_not_accumulates(): void
    {
        $accountant = Role::create(['name' => 'accountant', 'display_name' => 'Accountant', 'is_system' => false]);
        $accountant->permissions()->sync(Permission::whereIn('name', ['programmes.view'])->pluck('id'));

        $auditor = Role::create(['name' => 'auditor', 'display_name' => 'Auditor', 'is_system' => false]);
        $auditor->permissions()->sync(Permission::whereIn('name', ['reports.view'])->pluck('id'));

        $user = User::create([
            'name' => 'Switcher', 'email' => 'switcher@escalation.test',
            'password' => bcrypt('password'), 'role' => 'accountant', 'status' => 'active',
        ]);
        $this->assertTrue($user->hasRole('accountant'));
        $this->assertTrue($user->hasPermission('programmes.view'));

        $this->actingAs($this->adminUser, 'sanctum');
        $this->patchJson("/api/admin/users/{$user->id}", ['role' => 'auditor'])->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->hasRole('auditor'));
        $this->assertTrue($user->hasPermission('reports.view'));
        // The old role's permission must be gone, not merely added-to.
        $this->assertFalse($user->hasRole('accountant'));
        $this->assertFalse($user->hasPermission('programmes.view'));
    }

    public function test_cannot_delete_a_role_still_assigned_to_users(): void
    {
        $custom = Role::create(['name' => 'inventory_clerk', 'display_name' => 'Inventory Clerk', 'is_system' => false]);
        User::create([
            'name' => 'Clerk', 'email' => 'clerk@escalation.test',
            'password' => bcrypt('password'), 'role' => 'inventory_clerk', 'status' => 'active',
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->deleteJson("/api/admin/roles/{$custom->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $custom->id]);
    }

    public function test_cannot_detach_a_users_primary_role_via_role_management(): void
    {
        $custom = Role::create(['name' => 'warehouse_staff', 'display_name' => 'Warehouse Staff', 'is_system' => false]);
        $user = User::create([
            'name' => 'Warehouse', 'email' => 'warehouse@escalation.test',
            'password' => bcrypt('password'), 'role' => 'warehouse_staff', 'status' => 'active',
        ]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->deleteJson("/api/admin/roles/{$custom->id}/users/{$user->id}");

        $response->assertStatus(422);
        $this->assertTrue($user->fresh()->hasRole('warehouse_staff'));
    }
}

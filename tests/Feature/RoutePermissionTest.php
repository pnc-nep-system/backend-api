<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RoutePermissionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $memberUser;
    protected User $adminUser;
    protected User $coordinatorUser;
    protected Organisation $organisation;
    protected ProgrammeEntry $programmeEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->programmeEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->organisation->id,
        ]);

        $this->memberUser = User::factory()->create([
            'organisation_id' => $this->organisation->id,
            'role' => 'member_org',
        ]);

        $this->adminUser = User::factory()->create([
            'organisation_id' => $this->organisation->id,
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'role' => 'nep_coordinator',
        ]);
    }

    // ==================== Programme Entry Routes ====================

    public function test_unauthenticated_cannot_access_programme_entries_index()
    {
        $response = $this->getJson('/api/programme-entries');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_access_programme_entries_index()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries');
        $response->assertOk();
    }

    public function test_nep_admin_can_access_programme_entries_index()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_programme_entries_index()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/programme-entries');
        $response->assertOk();
    }

    public function test_unauthenticated_cannot_create_programme_entry()
    {
        $response = $this->postJson('/api/programme-entries', [
            'programme_name' => 'Test Programme',
            'start_year' => 2026,
        ]);
        $response->assertUnauthorized();
    }

    public function test_member_org_can_create_programme_entry()
    {
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Test Programme',
                'start_year' => 2026,
            ]);
        $response->assertCreated();
    }

    public function test_nep_admin_can_create_programme_entry()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Test Programme',
                'start_year' => 2026,
                'organisation_id' => $this->organisation->id,
            ]);
        $response->assertCreated();
    }

    public function test_nep_coordinator_can_create_programme_entry()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Test Programme',
                'start_year' => 2026,
                'organisation_id' => $this->organisation->id,
            ]);
        $response->assertCreated();
    }

    public function test_unauthenticated_cannot_update_programme_entry()
    {
        $response = $this->putJson("/api/programme-entries/{$this->programmeEntry->id}", [
            'programme_name' => 'Updated Programme',
        ]);
        $response->assertUnauthorized();
    }

    public function test_member_org_can_update_own_programme_entry()
    {
        $response = $this->actingAs($this->memberUser)
            ->putJson("/api/programme-entries/{$this->programmeEntry->id}", [
                'programme_name' => 'Updated Programme',
            ]);
        $response->assertOk();
    }

    public function test_nep_admin_can_update_any_programme_entry()
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/programme-entries/{$this->programmeEntry->id}", [
                'programme_name' => 'Updated Programme',
            ]);
        $response->assertOk();
    }

    public function test_nep_coordinator_can_update_any_programme_entry()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->putJson("/api/programme-entries/{$this->programmeEntry->id}", [
                'programme_name' => 'Updated Programme',
            ]);
        $response->assertOk();
    }

    public function test_unauthenticated_cannot_view_programme_entry()
    {
        $response = $this->getJson("/api/programme-entries/{$this->programmeEntry->id}");
        $response->assertUnauthorized();
    }

    public function test_member_org_can_view_own_programme_entry()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson("/api/programme-entries/{$this->programmeEntry->id}");
        $response->assertOk();
    }

    public function test_nep_admin_can_view_any_programme_entry()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/programme-entries/{$this->programmeEntry->id}");
        $response->assertOk();
    }

    public function test_nep_coordinator_can_view_any_programme_entry()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson("/api/programme-entries/{$this->programmeEntry->id}");
        $response->assertOk();
    }

    // ==================== Draft/Submitted Routes (member_org only) ====================

    public function test_unauthenticated_cannot_access_draft_entries()
    {
        $response = $this->getJson('/api/programme-entries/draft');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_access_draft_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/draft');
        $response->assertOk();
    }

    public function test_nep_admin_cannot_access_draft_entries()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries/draft');
        $response->assertForbidden();
    }

    public function test_nep_coordinator_cannot_access_draft_entries()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/programme-entries/draft');
        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_submitted_entries()
    {
        $response = $this->getJson('/api/programme-entries/submitted');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_access_submitted_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/submitted');
        $response->assertOk();
    }

    public function test_nep_admin_can_access_submitted_entries()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries/submitted');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_submitted_entries()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/programme-entries/submitted');
        $response->assertOk();
    }

    // ==================== Organisation Profile Routes (all authenticated roles) ====================

    public function test_unauthenticated_cannot_access_organisation_profile()
    {
        $response = $this->getJson('/api/organisations/me');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_view_own_organisation_profile()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/organisations/me');
        $response->assertOk();
    }

    public function test_nep_admin_can_access_organisation_profile()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/organisations/me');
        // Returns 404 if user has no organisation
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_organisation_profile()
    {
        // Coordinator has no organisation, so returns 404
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/organisations/me');
        $response->assertNotFound();
    }

    public function test_unauthenticated_cannot_update_organisation_profile()
    {
        $response = $this->patchJson('/api/organisations/me', [
            'contact_name' => 'New Contact',
        ]);
        $response->assertUnauthorized();
    }

    public function test_member_org_can_update_own_organisation_profile()
    {
        $response = $this->actingAs($this->memberUser)
            ->patchJson('/api/organisations/me', [
                'contact_name' => 'New Contact',
            ]);
        $response->assertOk();
    }

    public function test_nep_admin_can_update_organisation_profile()
    {
        $response = $this->actingAs($this->adminUser)
            ->patchJson('/api/organisations/me', [
                'contact_name' => 'New Contact',
            ]);
        $response->assertOk();
    }

    public function test_nep_coordinator_can_update_organisation_profile()
    {
        // Coordinator has no organisation, so returns 404
        $response = $this->actingAs($this->coordinatorUser)
            ->patchJson('/api/organisations/me', [
                'contact_name' => 'New Contact',
            ]);
        $response->assertNotFound();
    }

    // ==================== Verify Route (nep_admin only) ====================

    public function test_unauthenticated_cannot_verify_programme_entry()
    {
        $response = $this->patchJson("/api/programme-entries/{$this->programmeEntry->id}/verify");
        $response->assertUnauthorized();
    }

    public function test_nep_admin_can_verify_programme_entry()
    {
        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/programme-entries/{$this->programmeEntry->id}/verify");
        $response->assertOk();
    }

    public function test_nep_coordinator_cannot_verify_programme_entry()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->patchJson("/api/programme-entries/{$this->programmeEntry->id}/verify");
        $response->assertForbidden();
    }

    public function test_member_org_cannot_verify_programme_entry()
    {
        $response = $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->programmeEntry->id}/verify");
        $response->assertForbidden();
    }

    // ==================== Admin User Management Routes (nep_admin only) ====================

    public function test_unauthenticated_cannot_access_admin_users()
    {
        $response = $this->getJson('/api/admin/users');
        $response->assertUnauthorized();
    }

    public function test_nep_admin_can_access_admin_users()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/users');
        $response->assertOk();
    }

    public function test_nep_coordinator_cannot_access_admin_users()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/admin/users');
        $response->assertForbidden();
    }

    public function test_member_org_cannot_access_admin_users()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/admin/users');
        $response->assertForbidden();
    }

    // ==================== Admin Organisation Routes (nep_admin only) ====================

    public function test_unauthenticated_cannot_access_admin_organisations()
    {
        $response = $this->getJson('/api/admin/organisations');
        $response->assertUnauthorized();
    }

    public function test_nep_admin_can_access_admin_organisations()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/organisations');
        $response->assertOk();
    }

    public function test_nep_coordinator_cannot_access_admin_organisations()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/admin/organisations');
        $response->assertForbidden();
    }

    public function test_member_org_cannot_access_admin_organisations()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/admin/organisations');
        $response->assertForbidden();
    }

    // ==================== Taxonomy Admin Routes (nep_admin only) ====================

    public function test_unauthenticated_cannot_create_taxonomy_category()
    {
        $response = $this->postJson('/api/taxonomy/categories', [
            'name' => 'Test Category',
        ]);
        $response->assertUnauthorized();
    }

    public function test_nep_admin_can_create_taxonomy_category()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/taxonomy/categories', [
                'name' => 'Test Category',
                'code' => 'test-category',
                'label' => 'Test Category Label',
            ]);
        $response->assertCreated();
    }

    public function test_nep_coordinator_cannot_create_taxonomy_category()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/taxonomy/categories', [
                'name' => 'Test Category',
                'code' => 'test-category',
                'label' => 'Test Category Label',
            ]);
        $response->assertForbidden();
    }

    public function test_member_org_cannot_create_taxonomy_category()
    {
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/taxonomy/categories', [
                'name' => 'Test Category',
                'code' => 'test-category',
                'label' => 'Test Category Label',
            ]);
        $response->assertForbidden();
    }

    // ==================== Dashboard Routes (nep_admin, nep_coordinator) ====================

    public function test_unauthenticated_cannot_access_dashboard_stats()
    {
        $response = $this->getJson('/api/dashboard/stats');
        $response->assertUnauthorized();
    }

    public function test_nep_admin_can_access_dashboard_stats()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/dashboard/stats');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_dashboard_stats()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/dashboard/stats');
        $response->assertOk();
    }

    public function test_member_org_cannot_access_dashboard_stats()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/dashboard/stats');
        $response->assertForbidden();
    }

    // ==================== Adviser Submission Routes (nep_admin, nep_coordinator) ====================

    public function test_unauthenticated_cannot_access_adviser_submissions()
    {
        $response = $this->getJson('/api/adviser/submissions');
        $response->assertUnauthorized();
    }

    public function test_nep_admin_can_access_adviser_submissions()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_adviser_submissions()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/adviser/submissions');
        $response->assertOk();
    }

    public function test_member_org_cannot_access_adviser_submissions()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/adviser/submissions');
        $response->assertForbidden();
    }

    // ==================== Map Entry Routes (all authenticated roles - controller handles scoping) ====================

    public function test_unauthenticated_cannot_access_map_entries()
    {
        $response = $this->getJson('/api/map/entries');
        $response->assertUnauthorized();
    }

    public function test_nep_admin_can_access_map_entries()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/map/entries');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_map_entries()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/map/entries');
        $response->assertOk();
    }

    public function test_member_org_can_access_map_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/map/entries');
        $response->assertOk();
    }

    // ==================== Reference Data Routes (all authenticated roles) ====================

    public function test_unauthenticated_cannot_access_education_levels()
    {
        $response = $this->getJson('/api/refdata/education-levels');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_access_education_levels()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/refdata/education-levels');
        $response->assertOk();
    }

    public function test_nep_admin_can_access_education_levels()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/refdata/education-levels');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_education_levels()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/refdata/education-levels');
        $response->assertOk();
    }

    // ==================== Location Routes (all authenticated roles) ====================

    public function test_unauthenticated_cannot_access_provinces()
    {
        $response = $this->getJson('/api/provinces');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_access_provinces()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/provinces');
        $response->assertOk();
    }

    public function test_nep_admin_can_access_provinces()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/provinces');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_provinces()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/provinces');
        $response->assertOk();
    }

    // ==================== User Session Routes (all authenticated roles) ====================

    public function test_unauthenticated_cannot_access_user_info()
    {
        $response = $this->getJson('/api/user');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_access_user_info()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/user');
        $response->assertOk();
    }

    public function test_nep_admin_can_access_user_info()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/user');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_user_info()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/user');
        $response->assertOk();
    }

    public function test_unauthenticated_cannot_access_session()
    {
        $response = $this->getJson('/api/session');
        $response->assertUnauthorized();
    }

    public function test_member_org_can_access_session()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/session');
        $response->assertOk();
    }

    public function test_nep_admin_can_access_session()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/session');
        $response->assertOk();
    }

    public function test_nep_coordinator_can_access_session()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/session');
        $response->assertOk();
    }

    public function test_unauthenticated_cannot_logout()
    {
        $response = $this->postJson('/api/logout');
        $response->assertUnauthorized();
    }

    // Note: Logout functionality requires session store which is not available in API testing
    // The RBAC is verified by the 401 response for unauthenticated users above
    // Actual logout success is tested in the controller's own test suite
}
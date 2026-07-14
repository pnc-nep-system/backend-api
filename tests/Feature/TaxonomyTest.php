<?php

namespace Tests\Feature;

use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TaxonomyTest extends TestCase
{
    use DatabaseTransactions;

    // ==================== CATEGORY TESTS ====================

    public function test_nep_admin_can_create_category(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        
        $response = $this->actingAs($admin)->postJson('/api/taxonomy/categories', [
            'code' => 'health',
            'label' => 'Health',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'code' => 'health',
                'label' => 'Health',
            ]);

        $this->assertDatabaseHas('taxonomy_categories', [
            'code' => 'health',
            'label' => 'Health',
        ]);
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $user = User::factory()->create(['role' => 'member_org']);
        
        $response = $this->actingAs($user)->postJson('/api/taxonomy/categories', [
            'code' => 'health',
            'label' => 'Health',
        ]);

        $response->assertStatus(403);
    }

    public function test_nep_admin_can_rename_category(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $category = ActivityCategory::factory()->create([
            'code' => 'education',
            'label' => 'Education',
            'version' => null,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/taxonomy/categories/{$category->id}", [
            'label' => 'Education and Skills',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'label' => 'Education and Skills',
            ]);

        $this->assertNotNull($category->fresh()->version);
    }

    public function test_nep_admin_can_deprecate_category(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $category = ActivityCategory::factory()->create([
            'code' => 'old_category',
            'label' => 'Old Category',
            'is_active' => true,
            'version' => null,
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/taxonomy/categories/{$category->id}/deprecate");

        $response->assertStatus(200)
            ->assertJson([
                'is_active' => false,
            ]);

        $this->assertNotNull($category->fresh()->version);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_deprecated_category_is_not_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $category = ActivityCategory::factory()->create([
            'code' => 'old_category',
            'label' => 'Old Category',
        ]);

        $this->actingAs($admin)->patchJson("/api/taxonomy/categories/{$category->id}/deprecate");

        $this->assertDatabaseHas('taxonomy_categories', [
            'id' => $category->id,
            'code' => 'old_category',
        ]);
    }

    // ==================== SUBCATEGORY TESTS ====================

    public function test_nep_admin_can_create_subcategory(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $category = ActivityCategory::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/taxonomy/subcategories', [
            'category_id' => $category->id,
            'code' => 'primary_ed',
            'label' => 'Primary Education',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'category_id' => $category->id,
                'code' => 'primary_ed',
                'label' => 'Primary Education',
            ]);

        $this->assertDatabaseHas('taxonomy_subcategories', [
            'category_id' => $category->id,
            'code' => 'primary_ed',
        ]);
    }

    public function test_nep_admin_can_rename_subcategory(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $subcategory = ActivitySubcategory::factory()->create([
            'label' => 'Primary Education',
            'version' => null,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/taxonomy/subcategories/{$subcategory->id}", [
            'label' => 'Primary and Secondary Education',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'label' => 'Primary and Secondary Education',
            ]);

        $this->assertNotNull($subcategory->fresh()->version);
    }

    public function test_nep_admin_can_deprecate_subcategory(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $subcategory = ActivitySubcategory::factory()->create([
            'is_active' => true,
            'version' => null,
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/taxonomy/subcategories/{$subcategory->id}/deprecate");

        $response->assertStatus(200)
            ->assertJson([
                'is_active' => false,
            ]);

        $this->assertNotNull($subcategory->fresh()->version);
        $this->assertFalse($subcategory->fresh()->is_active);
    }

    // ==================== ITEM TESTS ====================

    public function test_nep_admin_can_create_item(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $subcategory = ActivitySubcategory::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/taxonomy/items', [
            'subcategory_id' => $subcategory->id,
            'code' => 'teacher_training',
            'label' => 'Teacher Training',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'subcategory_id' => $subcategory->id,
                'code' => 'teacher_training',
                'label' => 'Teacher Training',
            ]);

        $this->assertDatabaseHas('taxonomy_items', [
            'subcategory_id' => $subcategory->id,
            'code' => 'teacher_training',
        ]);
    }

    public function test_nep_admin_can_rename_item(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $item = ActivityItem::factory()->create([
            'label' => 'Teacher Training',
            'version' => null,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/taxonomy/items/{$item->id}", [
            'label' => 'Teacher Training and Development',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'label' => 'Teacher Training and Development',
            ]);

        $this->assertNotNull($item->fresh()->version);
    }

    public function test_nep_admin_can_deprecate_item(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $item = ActivityItem::factory()->create([
            'is_active' => true,
            'version' => null,
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/taxonomy/items/{$item->id}/deprecate");

        $response->assertStatus(200)
            ->assertJson([
                'is_active' => false,
            ]);

        $this->assertNotNull($item->fresh()->version);
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_deprecated_item_does_not_break_historical_entries(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $organisation = \App\Models\Organisation::factory()->create();
        $memberOrg = User::factory()->create([
            'role' => 'member_org',
            'organisation_id' => $organisation->id,
        ]);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $organisation->id]);
        $item = ActivityItem::factory()->create(['is_active' => true]);

        // Create a programme activity with the item
        $activity = ProgrammeActivity::create([
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
            'is_primary' => true,
            'source' => 'human_entered',
            'taxonomy_version' => $item->version,
        ]);

        // Deprecate the item
        $this->actingAs($admin)->patchJson("/api/taxonomy/items/{$item->id}/deprecate");

        // Verify the programme activity still exists
        $this->assertDatabaseHas('programme_activities', [
            'id' => $activity->id,
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
        ]);
    }

    // ==================== VERSION TRACKING TESTS ====================

    public function test_programme_activity_captures_taxonomy_version(): void
    {
        $organisation = \App\Models\Organisation::factory()->create();
        $memberOrg = User::factory()->create([
            'role' => 'member_org',
            'organisation_id' => $organisation->id,
        ]);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $organisation->id]);
        $item = ActivityItem::factory()->create([
            'version' => '2026-07-14T10:00:00Z',
        ]);
        $educationLevel = \App\Models\EducationLevel::factory()->create();

        $response = $this->actingAs($memberOrg)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            [
                'activities' => [
                    [
                        'activity_item_id' => $item->id,
                        'is_primary' => true,
                        'education_level_ids' => [$educationLevel->id],
                    ],
                ],
            ]
        );

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('programme_activities', [
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
            'taxonomy_version' => '2026-07-14T10:00:00Z',
        ]);
    }

    public function test_renaming_item_updates_version(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $item = ActivityItem::factory()->create([
            'label' => 'Old Label',
            'version' => '2026-07-14T09:00:00Z',
        ]);

        $this->actingAs($admin)->putJson("/api/taxonomy/items/{$item->id}", [
            'label' => 'New Label',
        ]);

        $updated = $item->fresh();
        $this->assertNotEquals('2026-07-14T09:00:00Z', $updated->version);
        $this->assertNotNull($updated->version);
    }

    // ==================== AUTHORIZATION TESTS ====================

    public function test_unauthenticated_user_cannot_access_taxonomy_endpoints(): void
    {
        $response = $this->getJson('/api/taxonomy/categories');
        $response->assertStatus(401);
    }

    public function test_member_org_cannot_access_taxonomy_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'member_org']);
        
        $response = $this->actingAs($user)->postJson('/api/taxonomy/categories', ['code' => 'test', 'label' => 'Test']);
        $response->assertStatus(403);
    }

    public function test_nep_coordinator_cannot_access_taxonomy_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'nep_coordinator']);
        
        $response = $this->actingAs($user)->postJson('/api/taxonomy/categories', ['code' => 'test', 'label' => 'Test']);
        $response->assertStatus(403);
    }

    // ==================== LISTING TESTS ====================

    public function test_list_categories_returns_nested_structure(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create(['category_id' => $category->id]);
        ActivityItem::factory()->create(['subcategory_id' => $subcategory->id]);

        $response = $this->actingAs($admin)->getJson('/api/taxonomy/categories');

        $response->assertStatus(200);
        
        // Verify the response contains our created category with nested structure
        $response->assertJsonFragment([
            'id' => $category->id,
        ]);
        
        // Verify the subcategory is nested within the category
        $response->assertJsonFragment([
            'id' => $subcategory->id,
            'category_id' => $category->id,
        ]);
        
        // Verify the item is nested within the subcategory
        $response->assertJsonFragment([
            'subcategory_id' => $subcategory->id,
        ]);
    }
}
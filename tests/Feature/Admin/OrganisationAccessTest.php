<?php

namespace Tests\Feature\Admin;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_nep_coordinator_can_list_organisations(): void
    {
        $coordinator = User::factory()->create(['role' => 'nep_coordinator', 'status' => 'active']);
        $organisation = Organisation::factory()->create(['name' => 'Partner Org']);

        $response = $this->actingAs($coordinator, 'sanctum')
            ->getJson('/api/admin/organisations');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $organisation->id);
        $response->assertJsonPath('data.0.name', 'Partner Org');
    }

    public function test_nep_coordinator_cannot_create_organisation(): void
    {
        $coordinator = User::factory()->create(['role' => 'nep_coordinator', 'status' => 'active']);

        $response = $this->actingAs($coordinator, 'sanctum')
            ->postJson('/api/admin/organisations', [
                'name' => 'Blocked Org',
                'contact_name' => 'Coordinator',
                'email' => 'blocked@example.org',
                'member_since' => 2026,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('organisations', ['email' => 'blocked@example.org']);
    }
}

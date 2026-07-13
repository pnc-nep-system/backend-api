<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_list_all_provinces(): void
    {
        $user = User::factory()->create();
        Province::create(['province_name' => 'Province A']);
        Province::create(['province_name' => 'Province B']);

        $response = $this->actingAs($user)->getJson('/api/provinces');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'province_name'],
                ],
            ]);
    }

    public function test_can_list_districts_for_valid_province(): void
    {
        $user = User::factory()->create();
        $province = Province::create(['province_name' => 'Test Province']);
        District::create(['province_id' => $province->id, 'name' => 'District 1']);
        District::create(['province_id' => $province->id, 'name' => 'District 2']);

        $response = $this->actingAs($user)->getJson("/api/provinces/{$province->id}/districts");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'District 1')
            ->assertJsonPath('data.1.name', 'District 2');
    }

    public function test_returns_empty_districts_for_province_with_no_districts(): void
    {
        $user = User::factory()->create();
        $province = Province::create(['province_name' => 'Empty Province']);

        $response = $this->actingAs($user)->getJson("/api/provinces/{$province->id}/districts");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_404_for_nonexistent_province(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/provinces/99999/districts');

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_provinces(): void
    {
        $response = $this->getJson('/api/provinces');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_districts(): void
    {
        $response = $this->getJson('/api/provinces/1/districts');

        $response->assertUnauthorized();
    }
}

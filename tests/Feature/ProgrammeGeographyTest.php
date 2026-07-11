<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProgrammeGeographyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_accepts_selected_province_with_zero_districts(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $province = Province::create(['province_name' => 'Test Province']);

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/geography",
            [
                'provinces' => [
                    [
                        'province_id' => $province->id,
                        'district_ids' => [],
                    ],
                ],
                'other_countries' => [],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Geography saved.')
            ->assertJsonPath('data.0.province_id', $province->id)
            ->assertJsonPath('data.0.district_id', null);

        $this->assertDatabaseHas('programme_geography', [
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => null,
            'country' => null,
        ]);
    }

    public function test_store_rejects_district_that_is_not_child_of_selected_province(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $selectedProvince = Province::create(['province_name' => 'Selected Province']);
        $otherProvince = Province::create(['province_name' => 'Other Province']);
        $otherDistrict = District::create([
            'province_id' => $otherProvince->id,
            'name' => 'Other District',
        ]);

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/geography",
            [
                'provinces' => [
                    [
                        'province_id' => $selectedProvince->id,
                        'district_ids' => [$otherDistrict->id],
                    ],
                ],
                'other_countries' => [],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('provinces.0.district_ids');
    }
}

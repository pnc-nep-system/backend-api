<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\GovernmentAgreement;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MapEntryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_province_filter_matches_direct_province(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_province_filter_matches_district_within_province(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }


    public function test_province_filter_excludes_other_provinces(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province1 = Province::create(['province_name' => 'Province 1']);
        $province2 = Province::create(['province_name' => 'Province 2']);
        $entry1 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province1->id,
            'district_id' => null,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province2->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province1->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_district_filter_works(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?district_id=' . $district->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_agreement_counterpart_type_filter_works(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?agreement_counterpart_type=MoEYS national level');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_agreement_status_filter_works(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?agreement_status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_combined_filters_work(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        // Entry 1: matches all filters
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry1->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);
        
        // Entry 2: matches province but not agreement status
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry2->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'expired',
            'institution_name' => 'Test Institution 2',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . '&agreement_status=active'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_nep_admin_sees_all_entries_with_filters(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $entry1 = ProgrammeEntry::factory()->create([
            'organisation_id' => $org1->id,
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'organisation_id' => $org2->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_member_org_only_sees_own_entries_with_filters(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $ownOrg->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $ownEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => $ownOrg->id,
        ]);
        $otherEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => $otherOrg->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $ownEntry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $otherEntry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownEntry->id);
    }
}
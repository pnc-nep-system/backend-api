<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\EntryKeyword;
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

    public function test_three_filters_applied_simultaneously_returns_intersected_results(): void
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
        
        // Entry 1: matches all 3 filters (province + district + agreement status)
        $entry1 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
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
        
        // Entry 2: matches province and district but NOT agreement status
        $entry2 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
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
        
        // Entry 3: matches agreement status but NOT province
        $entry3 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $otherProvince = Province::create(['province_name' => 'Other Province']);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry3->id,
            'province_id' => $otherProvince->id,
            'district_id' => null,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry3->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution 3',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . '&district_id=' . $district->id . '&agreement_status=active'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_no_duplicate_rows_when_entry_matches_multiple_joined_rows(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district1 = District::create([
            'province_id' => $province->id,
            'name' => 'District 1',
        ]);
        $district2 = District::create([
            'province_id' => $province->id,
            'name' => 'District 2',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        // Create multiple locations for the same entry (one in province directly, one in district)
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district1->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district2->id,
        ]);
        
        // Create multiple agreements for the same entry
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Institution 1',
            'nature' => 'MoU',
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'Provincial Office of Education',
            'status' => 'active',
            'institution_name' => 'Institution 2',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . '&agreement_status=active'
        );

        // Should return exactly 1 entry, not duplicates
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_four_filters_applied_simultaneously(): void
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
        
        // Entry 1: matches all 4 filters
        $entry1 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
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
        
        // Entry 2: matches 3 filters but not district
        $entry2 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry2->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution 2',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . 
            '&district_id=' . $district->id . 
            '&agreement_status=active' .
            '&agreement_counterpart_type=MoEYS national level'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_province_and_district_filters_require_same_location(): void
    {
        // This test validates the code review fix: province and district must come from the SAME location record
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province1 = Province::create(['province_name' => 'Province 1']);
        $province2 = Province::create(['province_name' => 'Province 2']);
        $district1 = District::create([
            'province_id' => $province1->id,
            'name' => 'District 1',
        ]);
        $district2 = District::create([
            'province_id' => $province2->id,
            'name' => 'District 2',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        // Entry has location in province1/district1 AND location in province2/district2
        // This should NOT match when filtering for province1 + district2 (different locations)
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province1->id,
            'district_id' => $district1->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province2->id,
            'district_id' => $district2->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province1->id . '&district_id=' . $district2->id
        );

        // Should NOT match because no single location has both province1 AND district2
        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_agreement_filters_require_same_agreement(): void
    {
        // This test validates the code review fix: counterpart type and status must come from the SAME agreement
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        
        // Agreement 1: MoEYS national level + active
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Institution 1',
            'nature' => 'MoU',
        ]);
        
        // Agreement 2: Provincial Office + expired
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'Provincial Office of Education',
            'status' => 'expired',
            'institution_name' => 'Institution 2',
            'nature' => 'MoU',
        ]);

        // This should NOT match because no single agreement has both MoEYS + expired
        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?agreement_counterpart_type=MoEYS national level&agreement_status=expired'
        );

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_csv_export_returns_csv_file(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
            'programme_name' => 'Test Programme',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');
        
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    public function test_csv_export_includes_all_key_fields(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
            'programme_name' => 'Test Programme',
            'start_year' => 2023,
            'end_year' => 2025,
            'ongoing' => false,
            'fte_staff' => 10.5,
            'direct_beneficiaries' => 100,
            'indirect_beneficiaries' => 500,
            'method' => 'Direct implementation',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();
        
        // Check for CSV headers
        $this->assertStringContainsString('Entry ID', $content);
        $this->assertStringContainsString('Programme Name', $content);
        $this->assertStringContainsString('Organisation Name', $content);
        $this->assertStringContainsString('Budget Band', $content);
        $this->assertStringContainsString('Start Year', $content);
        $this->assertStringContainsString('End Year', $content);
        $this->assertStringContainsString('Ongoing', $content);
        $this->assertStringContainsString('FTE Staff', $content);
        $this->assertStringContainsString('Direct Beneficiaries', $content);
        $this->assertStringContainsString('Indirect Beneficiaries', $content);
        $this->assertStringContainsString('Method', $content);
        $this->assertStringContainsString('Keywords', $content);
        $this->assertStringContainsString('Locations', $content);
        $this->assertStringContainsString('Activities', $content);
        $this->assertStringContainsString('Education Levels', $content);
        $this->assertStringContainsString('Government Agreements', $content);
    }

    public function test_csv_export_respects_filters(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
            'programme_name' => 'Programme in Province',
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
            'programme_name' => 'Programme Outside Province',
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export?province_id=' . $province->id);

        $response->assertOk();
        $content = $response->getContent();
        
        // Should contain entry1 but not entry2
        $this->assertStringContainsString('Programme in Province', $content);
        $this->assertStringNotContainsString('Programme Outside Province', $content);
    }

    public function test_csv_export_respects_permissions(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $org1->id,
            'role' => 'member_org',
        ]);
        
        $ownEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => $org1->id,
            'programme_name' => 'Own Programme',
        ]);
        $otherEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => $org2->id,
            'programme_name' => 'Other Programme',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();
        
        // Should contain own entry but not other entry
        $this->assertStringContainsString('Own Programme', $content);
        $this->assertStringNotContainsString('Other Programme', $content);
    }

    public function test_csv_export_nep_admin_sees_all_entries(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'organisation_id' => $org1->id,
            'programme_name' => 'Org1 Programme',
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'organisation_id' => $org2->id,
            'programme_name' => 'Org2 Programme',
        ]);

        $response = $this->actingAs($admin)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();
        
        // Admin should see both entries
        $this->assertStringContainsString('Org1 Programme', $content);
        $this->assertStringContainsString('Org2 Programme', $content);
    }

    public function test_csv_export_includes_related_data(): void
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
        
        // Add keyword
        EntryKeyword::create([
            'programme_entry_id' => $entry->id,
            'keyword' => 'education',
        ]);
        
        // Add location
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        
        // Add government agreement
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();
        
        // Check that related data is included
        $this->assertStringContainsString('education', $content);
        $this->assertStringContainsString('Test Province', $content);
        $this->assertStringContainsString('Test District', $content);
        $this->assertStringContainsString('MoEYS national level', $content);
        $this->assertStringContainsString('active', $content);
    }
}

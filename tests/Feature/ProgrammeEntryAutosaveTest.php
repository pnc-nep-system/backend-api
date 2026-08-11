<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgrammeEntryAutosaveTest extends TestCase
{
    use RefreshDatabase;

    protected User $memberUser;
    protected User $adminUser;
    protected User $otherUser;
    protected ProgrammeEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberUser = User::factory()->create([
            'role' => 'member_org',
            'organisation_id' => Organisation::factory(),
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'nep_admin',
            'organisation_id' => $this->memberUser->organisation_id,
        ]);

        $this->otherUser = User::factory()->create([
            'role' => 'member_org',
            'organisation_id' => Organisation::factory(),
        ]);

        $this->entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->memberUser->organisation_id,
            'start_year' => 2020,
            // The factory randomizes this — pin it so the forbidden-fields
            // test can assert autosave never writes it.
            'verified_date' => null,
        ]);
    }

    public function test_member_org_can_autosave_own_entry(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'programme_name' => 'Autosaved Programme',
                'direct_beneficiaries' => 250,
            ]);

        $response->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('saved_fields', ['programme_name', 'direct_beneficiaries'])
            ->assertJsonPath('skipped_fields', [])
            ->assertJsonStructure(['updated_at']);

        $this->entry->refresh();
        $this->assertEquals('Autosaved Programme', $this->entry->programme_name);
        $this->assertEquals(250, $this->entry->direct_beneficiaries);
    }

    public function test_autosave_skips_invalid_fields_while_typing(): void
    {
        // Simulates a debounced save firing mid-typing: name is complete,
        // start_year is only partially typed ("20").
        $response = $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'programme_name' => 'Typed Name',
                'start_year' => '20',
            ]);

        $response->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('saved_fields', ['programme_name'])
            ->assertJsonPath('skipped_fields', ['start_year']);

        $this->entry->refresh();
        $this->assertEquals('Typed Name', $this->entry->programme_name);
        $this->assertEquals(2020, $this->entry->start_year); // unchanged
    }

    public function test_autosave_skips_invalid_end_year_but_saves_valid_start_year(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'start_year' => 2026,
                'end_year' => 2020, // earlier than start_year
            ]);

        $response->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('saved_fields', ['start_year'])
            ->assertJsonPath('skipped_fields', ['end_year']);

        $this->entry->refresh();
        $this->assertEquals(2026, $this->entry->start_year);
        $this->assertNotEquals(2020, $this->entry->end_year);
    }

    public function test_autosave_can_save_end_year_alone_against_stored_start_year(): void
    {
        // Editing only end_year must validate against the stored start_year.
        $response = $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'end_year' => 2027,
            ]);

        $response->assertOk()->assertJsonPath('saved', true);

        $this->entry->refresh();
        $this->assertEquals(2027, $this->entry->end_year);
    }

    public function test_autosave_never_changes_submission_status(): void
    {
        $this->entry->update(['is_submitted' => true]);

        $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'programme_name' => 'Still Submitted',
            ])
            ->assertOk();

        $this->entry->refresh();
        $this->assertTrue($this->entry->is_submitted);
    }

    public function test_admin_autosave_forces_draft(): void
    {
        $this->entry->update(['is_submitted' => true]);

        $this->actingAs($this->adminUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'programme_name' => 'Admin Edited',
            ])
            ->assertOk();

        $this->entry->refresh();
        $this->assertFalse($this->entry->is_submitted);
    }

    public function test_autosave_sets_last_updated_by_and_clears_unverified(): void
    {
        $this->entry->update(['is_unverified' => true]);

        $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'programme_name' => 'Tracked Save',
            ])
            ->assertOk();

        $this->entry->refresh();
        $this->assertEquals($this->memberUser->id, $this->entry->last_updated_by);
        $this->assertFalse($this->entry->is_unverified);
    }

    public function test_member_org_cannot_autosave_other_org_entry(): void
    {
        $otherEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->otherUser->organisation_id,
        ]);

        $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$otherEntry->id}/autosave", [
                'programme_name' => 'Nope',
            ])
            ->assertForbidden();

        $otherEntry->refresh();
        $this->assertNotEquals('Nope', $otherEntry->programme_name);
    }

    public function test_autosave_with_no_fields_returns_not_saved(): void
    {
        $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [])
            ->assertOk()
            ->assertJsonPath('saved', false);
    }

    public function test_autosave_with_only_invalid_fields_returns_not_saved(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'start_year' => '2',
                'end_year' => 'x',
            ]);

        $response->assertOk()
            ->assertJsonPath('saved', false)
            ->assertJsonPath('skipped_fields', ['start_year', 'end_year']);

        $this->entry->refresh();
        $this->assertEquals(2020, $this->entry->start_year);
    }

    public function test_autosave_ignores_forbidden_fields(): void
    {
        // is_submitted / verified_date / organisation_id must be ignored.
        $this->actingAs($this->memberUser)
            ->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
                'programme_name' => 'Clean Save',
                'is_submitted' => true,
                'verified_date' => '2026-01-01',
                'organisation_id' => $this->otherUser->organisation_id,
            ])
            ->assertOk();

        $this->entry->refresh();
        $this->assertEquals('Clean Save', $this->entry->programme_name);
        $this->assertFalse($this->entry->is_submitted);
        $this->assertNull($this->entry->verified_date);
        $this->assertEquals($this->memberUser->organisation_id, $this->entry->organisation_id);
    }

    public function test_unauthenticated_cannot_autosave(): void
    {
        $this->patchJson("/api/programme-entries/{$this->entry->id}/autosave", [
            'programme_name' => 'Nope',
        ])->assertUnauthorized();
    }
}

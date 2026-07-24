<?php

namespace Tests\Feature;

use App\Models\ActivityItem;
use App\Models\EducationLevel;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use App\Services\AI\GroqService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProgrammeActivityTest extends TestCase
{
    use DatabaseTransactions;

    protected function validPayload(ActivityItem $item, EducationLevel $level): array
    {
        return [
            'activities' => [
                [
                    'activity_item_id' => $item->id,
                    'is_primary' => true,
                    'education_level_ids' => [$level->id],
                ],
            ],
        ];
    }

    public function test_member_org_can_write_to_own_entry(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(201);
    }

    public function test_member_org_cannot_write_to_another_organisations_entry(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $ownOrg->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $otherOrg->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(404);
    }

    public function test_nep_admin_can_write_to_any_entry(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(201);
    }

    public function test_nep_coordinator_is_blocked_from_writing(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinator = User::factory()->create(['role' => 'nep_coordinator']);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($coordinator)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(403);
    }

    public function test_other_activity_requires_free_text(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create([
            'is_active' => true,
            'is_other' => true,
        ]);
        $level = EducationLevel::factory()->create();
        $payload = $this->validPayload($item, $level);
        $payload['activities'][0]['other_text'] = '   ';

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $payload
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['activities.0.other_text']);
    }

    public function test_other_activity_free_text_is_stored_in_review_queue(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create([
            'is_active' => true,
            'is_other' => true,
        ]);
        $level = EducationLevel::factory()->create();
        $payload = $this->validPayload($item, $level);
        $payload['activities'][0]['other_text'] = 'Community radio literacy programme';

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $payload
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('taxonomy_other_queues', [
            'programme_entry_id' => $entry->id,
            'item_id' => $item->id,
            'other_text' => 'Community radio literacy programme',
            'suggested_subcategory_id' => $item->subcategory_id,
            'status' => 'pending',
        ]);
    }

    public function test_suggest_activities_accepts_ai_codes_without_leading_zero(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);
        $item = ActivityItem::factory()->create([
            'code' => 'Z9.9.01',
            'label' => 'Scholarships',
            'is_active' => true,
            'is_other' => false,
        ]);

        $groq = \Mockery::mock(GroqService::class);
        $groq->shouldReceive('generateContent')
            ->once()
            ->with(\Mockery::on(fn (string $prompt) => str_contains($prompt, 'Z9.9.01: Scholarships')))
            ->andReturn(['_raw_array' => ['Z9.9.1']]);

        $this->app->instance(GroqService::class, $groq);

        $response = $this->actingAs($user)->postJson('/api/programme-entries/suggest-activities', [
            'text' => 'This programme provides scholarships for learners to stay enrolled in school.',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'data' => [$item->code],
            ]);
    }

    public function test_suggest_activities_returns_clear_error_when_pdf_has_no_readable_text(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);
        $pdf = UploadedFile::fake()->createWithContent(
            'scanned.pdf',
            "%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        $response = $this->actingAs($user)->postJson('/api/programme-entries/suggest-activities', [
            'file' => $pdf,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text'])
            ->assertJsonPath('message', 'No readable programme description was found. Please paste the programme description text, or upload a text-based PDF. Scanned/image PDFs need OCR before AI suggestions can be generated.');
    }

    public function test_suggest_activities_extracts_text_from_uploaded_pdf(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);
        $item = ActivityItem::factory()->create([
            'code' => 'Z9.9.02',
            'label' => 'Teacher training',
            'is_active' => true,
            'is_other' => false,
        ]);

        $pdfContent = Pdf::loadHTML('<p>This programme trains teachers in inclusive classroom practices.</p>')->output();
        $pdf = UploadedFile::fake()->createWithContent('programme.pdf', $pdfContent);

        $groq = \Mockery::mock(GroqService::class);
        $groq->shouldReceive('generateContent')
            ->once()
            ->with(\Mockery::on(fn (string $prompt) => str_contains($prompt, 'trains teachers in inclusive classroom practices')))
            ->andReturn(['_raw_array' => ['Z9.9.02']]);

        $this->app->instance(GroqService::class, $groq);

        $response = $this->actingAs($user)->postJson('/api/programme-entries/suggest-activities', [
            'file' => $pdf,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'data' => [$item->code],
            ]);
    }

    public function test_suggest_activities_rejects_raw_pdf_content_sent_as_text(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($user)->postJson('/api/programme-entries/suggest-activities', [
            'text' => "%PDF-1.7\n5 0 obj\n<</Filter /FlateDecode/Length 4781>>\nstream\nx raw compressed pdf bytes\nendstream",
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text'])
            ->assertJsonPath('message', 'The uploaded PDF was sent as raw PDF data, not readable programme text. Extract the PDF text first, use OCR for scanned PDFs, or paste the programme description.');
    }
}

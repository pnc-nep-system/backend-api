<?php

namespace Tests\Feature;

use App\Models\AdvisoryNote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MistralIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $coordinatorUser;
    protected User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'role' => 'nep_coordinator',
        ]);

        $this->memberUser = User::factory()->create([
            'role' => 'member_org',
        ]);
    }

    // ----------------------------------------------------------------
    //  Permission checks
    // ----------------------------------------------------------------

    public function test_nep_admin_can_generate_advisory_note()
    {
        $submission = AdvisoryNote::factory()->create([
            'status' => 'pending',
            'analysis_scope' => 'full map',
        ]);

        // Mock the Mistral API response
        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'executive_summary' => 'Test executive summary.',
                                'similar_or_overlapping_programmes' => [],
                                'potential_duplication' => 'No duplication detected.',
                                'coverage_gaps' => 'No gaps identified.',
                                'recommendations' => 'Proceed with submission.',
                                'confidence_notes' => 'Test confidence note.',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [1],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Advisory note generated successfully.',
        ]);
        $response->assertJsonStructure([
            'data' => [
                'executive_summary',
                'similar_or_overlapping_programmes',
                'potential_duplication',
                'coverage_gaps',
                'recommendations',
                'confidence_notes',
            ],
        ]);
    }

    public function test_nep_coordinator_can_generate_advisory_note()
    {
        $submission = AdvisoryNote::factory()->create([
            'status' => 'pending',
            'analysis_scope' => 'full map',
        ]);

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'executive_summary' => 'Test summary.',
                                'similar_or_overlapping_programmes' => [],
                                'potential_duplication' => 'None.',
                                'coverage_gaps' => 'None.',
                                'recommendations' => 'Proceed.',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->coordinatorUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Advisory note generated successfully.');
    }

    public function test_member_org_cannot_generate_advisory_note()
    {
        $submission = AdvisoryNote::factory()->create();

        $response = $this->actingAs($this->memberUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_generate_advisory_note()
    {
        $submission = AdvisoryNote::factory()->create();

        $response = $this->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
            'programme_profile' => [
                'activities' => [],
                'geography' => [],
                'audiences' => [],
            ],
        ]);

        $response->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    //  Validation
    // ----------------------------------------------------------------

    public function test_validates_programme_profile_is_required()
    {
        $submission = AdvisoryNote::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('programme_profile');
    }

    public function test_returns_404_for_nonexistent_submission()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions/99999/generate-advisory-note', [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    //  AI Response Parsing
    // ----------------------------------------------------------------

    public function test_parses_valid_json_response_from_mistral()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        $expectedResponse = [
            'executive_summary' => 'The submitted programme focuses on primary education in rural areas.',
            'similar_or_overlapping_programmes' => [
                [
                    'programme_name' => 'Education for All',
                    'organisation' => 'Ministry of Education',
                    'overlap_type' => 'activity',
                    'description' => 'Both programmes target primary education.',
                ],
            ],
            'potential_duplication' => 'There is potential duplication with Education for All in primary education activities.',
            'coverage_gaps' => 'The programme could address gaps in early childhood education.',
            'recommendations' => 'Consider coordinating with Education for All to avoid duplication.',
            'confidence_notes' => 'Analysis based on full map scope with 1 overlapping programme identified.',
        ];

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($expectedResponse),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [1],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.executive_summary', $expectedResponse['executive_summary']);
        $response->assertJsonPath('data.potential_duplication', $expectedResponse['potential_duplication']);
        $response->assertJsonPath('data.coverage_gaps', $expectedResponse['coverage_gaps']);
        $response->assertJsonPath('data.recommendations', $expectedResponse['recommendations']);
        $response->assertJsonCount(1, 'data.similar_or_overlapping_programmes');
    }

    public function test_parses_json_from_markdown_code_block()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        // Simulate Mistral returning JSON inside a markdown code block
        $markdownResponse = "Here is the analysis:\n\n```json\n{\n  \"executive_summary\": \"Summary from markdown.\",\n  \"similar_or_overlapping_programmes\": [],\n  \"potential_duplication\": \"None.\",\n  \"coverage_gaps\": \"None.\",\n  \"recommendations\": \"Proceed.\",\n  \"confidence_notes\": \"From markdown.\"\n}\n```";

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $markdownResponse,
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.executive_summary', 'Summary from markdown.');
    }

    public function test_handles_non_json_response_gracefully()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        // Simulate Mistral returning plain text instead of JSON
        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'This is a plain text response without any JSON structure.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        // Should still return a response with the raw text in executive_summary
        $response->assertJsonStructure([
            'data' => [
                'executive_summary',
                'similar_or_overlapping_programmes',
                'potential_duplication',
                'coverage_gaps',
                'recommendations',
                'confidence_notes',
            ],
        ]);
    }

    // ----------------------------------------------------------------
    //  Error Handling
    // ----------------------------------------------------------------

    public function test_handles_mistral_api_failure()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        // Simulate a 500 error from Gemini
        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Internal server error',
                ],
            ], 500),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Mistral API returned error status 500: AI service is temporarily unavailable. Please try again later.',
        ]);
    }

    public function test_handles_mistral_api_authentication_failure()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Permission denied.',
                ],
            ], 403),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Mistral API returned error status 403: Authentication failed. Check API key configuration.',
        ]);
    }

    public function test_handles_mistral_api_rate_limit()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded.',
                ],
            ], 429),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Mistral API returned error status 429: Rate limit exceeded. Please wait and try again.',
        ]);
    }

    public function test_handles_network_failure()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        // Simulate a connection error
        Http::fake([
            'api.mistral.ai/v1/chat/completions' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertStatus(503);
        $response->assertJson([
            'message' => 'Unable to connect to Mistral AI service. Please try again later.',
        ]);
    }

    public function test_handles_empty_mistral_response()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        // Simulate an empty response from Mistral
        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([], 200),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertStatus(503);
        $response->assertJson([
            'message' => 'Mistral API returned an empty response.',
        ]);
    }

    public function test_handles_blocked_content()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        // Simulate Mistral blocking the content
        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'message' => 'Content blocked due to safety concerns',
            ], 400),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Mistral API returned error status 400: Invalid request: Content blocked due to safety concerns',
        ]);
    }

    // ----------------------------------------------------------------
    //  Prompt Builder Tests
    // ----------------------------------------------------------------

    public function test_prompt_builder_generates_valid_prompt()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'full map',
        ]);

        $expectedResponse = [
            'executive_summary' => 'Test summary.',
            'similar_or_overlapping_programmes' => [],
            'potential_duplication' => 'None.',
            'coverage_gaps' => 'None.',
            'recommendations' => 'Proceed.',
        ];

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => function ($request) use ($expectedResponse) {
                // Verify the request contains expected prompt sections
                $body = $request->body();
                $this->assertStringContainsString('SUBMITTED PROGRAMME PROFILE', $body);
                $this->assertStringContainsString('OVERLAPPING PROGRAMMES', $body);
                $this->assertStringContainsString('OUTPUT FORMAT', $body);
                $this->assertStringContainsString('executive_summary', $body);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode($expectedResponse),
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                ], 200);
            },
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [1],
                        'education_level_ids' => [2],
                        'inclusion_groups' => ['boys'],
                    ],
                    'geography' => [
                        'province_ids' => [3],
                    ],
                    'audiences' => [
                        'inclusion_types' => ['target'],
                    ],
                ],
            ]);

        $response->assertOk();
    }

    public function test_prompt_builder_includes_scope_detail()
    {
        $submission = AdvisoryNote::factory()->create([
            'analysis_scope' => 'geographic subset',
            'analysis_scope_detail' => 'Focus on Phnom Penh province',
        ]);

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => function ($request) {
                $body = $request->body();
                $this->assertStringContainsString('geographic subset', $body);
                $this->assertStringContainsString('Focus on Phnom Penh province', $body);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'executive_summary' => 'Test.',
                                    'similar_or_overlapping_programmes' => [],
                                    'potential_duplication' => 'None.',
                                    'coverage_gaps' => 'None.',
                                    'recommendations' => 'Proceed.',
                                ]),
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                ], 200);
            },
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
    }

    public function test_updates_submission_status_to_analysed()
    {
        $submission = AdvisoryNote::factory()->create([
            'status' => 'pending',
            'analysis_scope' => 'full map',
        ]);

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'executive_summary' => 'Test.',
                                'similar_or_overlapping_programmes' => [],
                                'potential_duplication' => 'None.',
                                'coverage_gaps' => 'None.',
                                'recommendations' => 'Proceed.',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->adminUser)
            ->postJson("/api/adviser/submissions/{$submission->id}/generate-advisory-note", [
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $this->assertDatabaseHas('advisory_notes', [
            'id' => $submission->id,
            'status' => 'analysed',
        ]);
    }
}
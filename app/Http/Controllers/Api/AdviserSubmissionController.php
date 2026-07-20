<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdviserSubmissionRequest;
use App\Http\Requests\StoreAdviserSubmissionRequest;
use App\Models\AdvisoryNote;
use App\Models\User;
use App\Services\Adviser\MapOverlapMatcher;
use App\Services\AI\MistralService;
use App\Services\AI\PromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "AdviserSubmission",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "submitting_party", type: "string", example: "Ministry of Education"),
        new OA\Property(property: "document_name", type: "string", example: "Education Sector Review 2026"),
        new OA\Property(property: "analysis_scope", type: "string", example: "full map", enum: ["full map", "geographic subset", "thematic subset"]),
        new OA\Property(property: "analysis_scope_detail", type: "string", example: "Focus on Phnom Penh and Siem Reap provinces", nullable: true),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "submitted_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "AdvisoryNoteResponse",
    type: "object",
    properties: [
        new OA\Property(property: "executive_summary", type: "string", example: "The submitted programme shows significant overlap with existing programmes in the education sector..."),
        new OA\Property(
            property: "similar_or_overlapping_programmes",
            type: "array",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "programme_name", type: "string"),
                    new OA\Property(property: "organisation", type: "string"),
                    new OA\Property(property: "overlap_type", type: "string", enum: ["activity", "geography", "audience", "multiple"]),
                    new OA\Property(property: "description", type: "string"),
                ]
            )
        ),
        new OA\Property(property: "potential_duplication", type: "string", example: "There is potential duplication with Programme X in the area of..."),
        new OA\Property(property: "coverage_gaps", type: "string", example: "The programme could address gaps in..."),
        new OA\Property(property: "recommendations", type: "string", example: "It is recommended to..."),
        new OA\Property(property: "confidence_notes", type: "string", example: "Analysis based on full map scope with 3 overlapping programmes identified.", nullable: true),
    ]
)]
class AdviserSubmissionController extends Controller
{
    #[OA\Post(
        path: "/adviser/submissions",
        summary: "Submit a document for Adviser analysis",
        description: "Allows NEP Coordinators and Admins to submit a document for analysis. The submitting party is captured as free text (not linked to the member database). Analysis scope defaults to 'full map' when not specified.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["submitting_party", "document_name"],
                properties: [
                    new OA\Property(
                        property: "submitting_party",
                        type: "string",
                        example: "Ministry of Education",
                        description: "Name of the submitting party (free text, not linked to member database)"
                    ),
                    new OA\Property(
                        property: "document_name",
                        type: "string",
                        example: "Education Sector Review 2026",
                        description: "Name or title of the document being submitted"
                    ),
                    new OA\Property(
                        property: "analysis_scope",
                        type: "string",
                        example: "full map",
                        description: "Scope of analysis: 'full map', 'geographic subset', or 'thematic subset'",
                        enum: ["full map", "geographic subset", "thematic subset"]
                    ),
                    new OA\Property(
                        property: "analysis_scope_detail",
                        type: "string",
                        example: "Focus on Phnom Penh and Siem Reap provinces",
                        description: "Additional details about the analysis scope (required for geographic or thematic subsets)",
                        nullable: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Document submitted successfully for analysis",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Document submitted for analysis."),
                        new OA\Property(property: "data", ref: "#/components/schemas/AdviserSubmission"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(
                response: 403,
                description: "Forbidden - Only NEP Coordinators and Admins can submit documents",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "Forbidden.")]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "The given data was invalid."),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    #[OA\Get(
        path: "/adviser/submissions",
        summary: "List Adviser document submissions",
        description: "Returns a paginated list of Adviser document submission records. Only NEP Coordinators and Admins can retrieve submission records. Supports filtering by analysis scope and status.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(name: "analysis_scope", in: "query", required: false, description: "Filter by analysis scope", schema: new OA\Schema(type: "string", enum: ["full map", "geographic subset", "thematic subset"])),
            new OA\Parameter(name: "status", in: "query", required: false, description: "Filter by submission status", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", required: false, description: "Number of records per page (default: 25, max: 100)", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Submission records retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/AdviserSubmission")
                        ),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "last_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 25),
                        new OA\Property(property: "total", type: "integer", example: 10),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(
                response: 403,
                description: "Forbidden - Only NEP Coordinators and Admins can retrieve submission records",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "Forbidden.")]
                )
            ),
        ]
    )]
    public function index(ListAdviserSubmissionRequest $request)
    {
        $query = AdvisoryNote::query();

        if ($request->filled('analysis_scope')) {
            $query->where('analysis_scope', $request->input('analysis_scope'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query->orderBy('submitted_at', 'desc');

        $perPage = $request->integer('per_page', 25);
        $submissions = $query->paginate($perPage);

        return response()->json($submissions);
    }

    public function store(StoreAdviserSubmissionRequest $request)
    {
        $validated = $request->validated();

        // Default to "full map" if not specified
        $validated['analysis_scope'] = $validated['analysis_scope'] ?? 'full map';

        // If analysis scope is full map, clear the detail field
        if ($validated['analysis_scope'] === 'full map') {
            $validated['analysis_scope_detail'] = null;
        }

        // Map assigned_to to assign_to_staff_user_id if present
        if (array_key_exists('assigned_to', $validated)) {
            $validated['assign_to_staff_user_id'] = $validated['assigned_to'];
            unset($validated['assigned_to']);
        }

        $submission = AdvisoryNote::create([
            ...$validated,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Document submitted for analysis.',
            'data' => $submission,
        ], 201);
    }

    public function show($id)
    {
        $submission = AdvisoryNote::findOrFail($id);
        return response()->json([
            'data' => $submission,
        ]);
    }

    /**
     * Get list of NEP coordinators for dropdown assignment
     */
    public function getCoordinators()
    {
        $coordinators = User::where('role', 'nep_coordinator')
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get();

        return response()->json($coordinators);
    }

    /**
     * Return all active users with the nep_coordinator role.
     * Accessible to both nep_admin and nep_coordinator so the assignment
     * dropdown can be populated without going through the admin-only /admin/users endpoint.
     */
    public function coordinators()
    {
        $coordinators = User::where('role', 'nep_coordinator')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json([
            'data' => $coordinators,
        ]);
    }
    #[OA\Post(
        path: "/adviser/submissions/{id}/generate-advisory-note",
        summary: "Generate an AI-powered advisory note using Mistral",
        description: "Takes a submitted programme profile, queries overlapping entries from the map, and generates a structured advisory note using Mistral AI.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "The advisory note submission ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["programme_profile"],
                properties: [
                    new OA\Property(
                        property: "programme_profile",
                        type: "object",
                        description: "The extracted programme profile for analysis",
                        example: [
                            "activities" => [
                                "category_ids" => [1],
                                "education_level_ids" => [2],
                                "inclusion_groups" => ["boys"],
                            ],
                            "audiences" => [
                                "inclusion_types" => ["target"],
                            ],
                            "geography" => [
                                "province_ids" => [3],
                            ],
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Advisory note generated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Advisory note generated successfully."),
                        new OA\Property(property: "data", ref: "#/components/schemas/AdvisoryNoteResponse"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(
                response: 403,
                description: "Forbidden - Only NEP Coordinators and Admins can generate advisory notes",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "Forbidden.")]
                )
            ),
            new OA\Response(response: 404, description: "Submission not found"),
            new OA\Response(
                response: 422,
                description: "Validation failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "The given data was invalid."),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: "AI service unavailable",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "AI service is temporarily unavailable. Please try again later."),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "AI service error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "An unexpected error occurred while generating advisory content."),
                    ]
                )
            ),
        ]
    )]
    public function generateAdvisoryNote(
        int $id,
        Request $request,
        MapOverlapMatcher $matcher,
        PromptBuilder $promptBuilder
    ): JsonResponse {
        $submission = AdvisoryNote::findOrFail($id);

        // Validate user permissions
        $user = $request->user();
        if (!$user->isNepAdmin() && $user->role !== 'nep_coordinator') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'programme_profile' => 'required|array',
            'programme_profile.activities' => 'sometimes|array',
            'programme_profile.geography' => 'sometimes|array',
            'programme_profile.audiences' => 'sometimes|array',
        ]);

        $programmeProfile = $request->input('programme_profile');
        $analysisScope = $submission->analysis_scope ?? 'full map';
        $analysisScopeDetail = $submission->analysis_scope_detail;

        // Query overlapping entries using the MapOverlapMatcher
        $overlappingEntries = $matcher
            ->match($programmeProfile, $analysisScope)
            ->with([
                'organisation',
                'budgetBand',
                'keywords',
                'locations.province',
                'locations.district',
                'locations.commune',
                'locations.village',
                'activities.activityItem.subcategory.category',
                'activities.activityItem.subcategory',
                'activities.activityItem',
                'activities.activityLevels.educationLevel',
            ])
            ->get();

        // Convert entries to array format for the prompt builder
        $entriesArray = $overlappingEntries->toArray();

        // Build the prompt
        $prompt = $promptBuilder->build(
            $programmeProfile,
            $entriesArray,
            $analysisScope,
            $analysisScopeDetail
        );

        try {
            // Resolve MistralService lazily to avoid instantiation errors when API key is not set
            $mistral = App::make(MistralService::class);
            // Send to Mistral and get structured response
            $aiResponse = $mistral->generateContent($prompt);

            // Update the submission status
            $submission->update(['status' => 'analysed']);

            return response()->json([
                'message' => 'Advisory note generated successfully.',
                'data' => $aiResponse,
            ]);
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode();

            // Map common HTTP status codes, default to 503
            $httpStatus = in_array($statusCode, [400, 401, 403, 404, 429, 500, 502, 503])
                ? $statusCode
                : 503;

            // Ensure we return a valid HTTP status code
            if ($httpStatus < 100 || $httpStatus > 599) {
                $httpStatus = 503;
            }

            // Log the error for debugging (without exposing sensitive details)
            Log::warning('Advisory note generation failed', [
                'submission_id' => $submission->id,
                'status_code' => $statusCode,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], $httpStatus);
        }
    }
}

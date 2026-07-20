<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdviserSubmissionRequest;
use App\Http\Requests\StoreAdviserSubmissionRequest;
use App\Models\AdvisoryNote;
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
}
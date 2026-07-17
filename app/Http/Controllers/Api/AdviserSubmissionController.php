<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeEntryRequest;
use App\Http\Requests\UpdateProgrammeEntryRequest;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ProgrammeEntry",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 45),
        new OA\Property(property: "organisation_id", type: "integer", example: 12),
        new OA\Property(property: "budget_band_id", type: "integer", example: 3, nullable: true),
        new OA\Property(property: "programme_name", type: "string", example: "Youth Skills Initiative"),
        new OA\Property(property: "start_year", type: "integer", example: 2026),
        new OA\Property(property: "end_year", type: "integer", example: 2027, nullable: true),
        new OA\Property(property: "ongoing", type: "boolean", example: false),
        new OA\Property(property: "fte_staff", type: "string", example: "2.50", description: "Decimal value serialized as string (Laravel decimal:2 cast)"),
        new OA\Property(property: "indirect_beneficiaries", type: "integer", example: 600),
        new OA\Property(property: "direct_beneficiaries", type: "integer", example: 150),
        new OA\Property(property: "method", type: "string", example: "Workshops and mentoring", nullable: true),
        new OA\Property(property: "verified_date", type: "string", format: "date", example: "2026-03-01", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class ProgrammeEntryController extends Controller
{
    #[OA\Post(
        path: "/programme-entries",
        summary: "Create a new programme entry",
        description: "Creates a Section 1 programme entry, automatically scoped to the authenticated user's organisation.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["programme_name", "start_year"],
                properties: [
                    new OA\Property(property: "programme_name", type: "string", example: "Youth Skills Initiative"),
                    new OA\Property(property: "start_year", type: "integer", example: 2026),
                    new OA\Property(property: "end_year", type: "integer", example: 2027, nullable: true),
                    new OA\Property(property: "ongoing", type: "boolean", example: false),
                    new OA\Property(property: "description", type: "string", example: "A short summary of the programme"),
                    new OA\Property(property: "fte_staff", type: "number", format: "float", example: 2.5),
                    new OA\Property(property: "direct_beneficiaries", type: "integer", example: 150),
                    new OA\Property(property: "indirect_beneficiaries", type: "integer", example: 600),
                    new OA\Property(property: "method", type: "string", example: "Workshops and mentoring"),
                    new OA\Property(property: "verified_date", type: "string", format: "date", example: "2026-03-01", nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Entry created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Programme entry created."),
                        new OA\Property(property: "data", ref: "#/components/schemas/ProgrammeEntry"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
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
    public function store(StoreProgrammeEntryRequest $request)
    {
        $entry = ProgrammeEntry::create([
            ...$request->validated(),
            'organisation_id' => $request->user()->organisation_id,
        ]);
        return response()->json([
            'message' => 'Programme entry created.',
            'data' => $entry,
        ], 201);
    }

    #[OA\Put(
        path: "/programme-entries/{id}",
        summary: "Update an existing programme entry",
        description: "Updates Section 1 fields. Only NEP Admins or the owning organisation may edit.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "programmeEntry",
                in: "path",
                required: true,
                description: "Programme entry ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "programme_name", type: "string", example: "Youth Skills Initiative"),
                    new OA\Property(property: "budget_band_id", type: "integer", example: 3, nullable: true),
                    new OA\Property(property: "start_year", type: "integer", example: 2026),
                    new OA\Property(property: "end_year", type: "integer", example: 2027, nullable: true),
                    new OA\Property(property: "ongoing", type: "boolean", example: false),
                    new OA\Property(property: "fte_staff", type: "number", format: "float", example: 2.5, description: "Accepts numeric input; stored/returned as decimal:2"),
                    new OA\Property(property: "indirect_beneficiaries", type: "integer", example: 600),
                    new OA\Property(property: "direct_beneficiaries", type: "integer", example: 150),
                    new OA\Property(property: "method", type: "string", example: "Workshops and mentoring", nullable: true),
                    new OA\Property(property: "verified_date", type: "string", format: "date", example: "2026-03-01", nullable: true),
                ],
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Entry updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Programme entry updated."),
                        new OA\Property(property: "data", ref: "#/components/schemas/ProgrammeEntry"),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: "Not authorized to update this entry",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "You are not authorized to update this entry.")]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Entry not found"),
            new OA\Response(
                response: 422,
                description: "Validation failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function update(UpdateProgrammeEntryRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canManage($request, $programmeEntry)) {
            return response()->json([
                'message' => 'You are not authorized to update this entry.',
            ], 403);
        }
        $programmeEntry->update($request->validated());

        return response()->json([
            'message' => 'Programme entry updated.',
            'data' => $programmeEntry->fresh(),
        ]);
    }

    #[OA\Get(
        path: "/programme-entries/{id}",
        summary: "Get a single programme entry",
        description: "Retrieves one programme entry. Only NEP Admins or the owning organisation may view.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "programmeEntry",
                in: "path",
                required: true,
                description: "Programme entry ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Entry retrieved successfully",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "data", ref: "#/components/schemas/ProgrammeEntry")]
                )
            ),
            new OA\Response(
                response: 403,
                description: "Not authorized to view this entry",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "You are not authorized to view this entry.")]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Entry not found"),
        ]
    )]
    public function show(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canManage($request, $programmeEntry)) {
            return response()->json([
                'message' => 'You are not authorized to view this entry.',
            ], 403);
        }
        return response()->json(['data' => $programmeEntry]);
    }

    protected function canManage(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        $user = $request->user();
        return $user->role === 'nep_admin'
            || $programmeEntry->organisation_id === $user->organisation_id;
    }
}

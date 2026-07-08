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
        new OA\Property(property: "programme_name", type: "string", example: "Youth Skills Initiative"),
        new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-01-15"),
        new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-12-31", nullable: true),
        new OA\Property(property: "description", type: "string", example: "A short summary of the programme"),
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
        security: [["sessionAuth" => []]],
        tags: ["Programme Entries"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["programme_name", "start_date"],
                properties: [
                    new OA\Property(property: "programme_name", type: "string", example: "Youth Skills Initiative"),
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-01-15"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-12-31", nullable: true),
                    new OA\Property(property: "description", type: "string", example: "A short summary of the programme"),
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
        security: [["sessionAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "id",
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
                    new OA\Property(property: "programme_name", type: "string", example: "Youth Skills Initiative v2"),
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-02-01"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-12-31", nullable: true),
                    new OA\Property(property: "description", type: "string", example: "Updated summary"),
                ]
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
        security: [["sessionAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "id",
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
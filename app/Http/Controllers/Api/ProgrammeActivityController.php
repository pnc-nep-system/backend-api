<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeActivityRequest;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProgrammeActivityController extends Controller
{
    #[OA\Schema(
        schema: "ProgrammeActivity",
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer", example: 78),
            new OA\Property(property: "programme_entry_id", type: "integer", example: 45),
            new OA\Property(property: "activity_item_id", type: "integer", example: 12),
            new OA\Property(property: "is_primary", type: "boolean", example: true),
            new OA\Property(property: "inclusion_group", type: "string", example: "gender", nullable: true),
            new OA\Property(property: "inclusion_type", type: "string", example: "girls", nullable: true),
            new OA\Property(property: "source", type: "string", enum: ["ai_confirmed", "ai_modified", "human_entered"]),
            new OA\Property(
                property: "activity_levels",
                type: "array",
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: "id", type: "integer"),
                        new OA\Property(property: "education_level_id", type: "integer"),
                    ]
                )
            ),
            new OA\Property(property: "created_at", type: "string", format: "date-time"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        ]
    )]


    #[OA\Post(
        path: "/programme-entries/{programmeEntry}/activities",
        summary: "Save Section 2 activity selections for a programme entry",
        description: "Accepts one or more activity selections, each with a taxonomy item, inclusion flag/group/type, multiple education levels, and a primary/secondary flag. Inclusion is recorded once per activity item, not per education level. Rejects inactive or deprecated taxonomy items.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Activities"],
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
                required: ["activities"],
                properties: [
                    new OA\Property(
                        property: "activities",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "activity_item_id", type: "integer", example: 12),
                                new OA\Property(property: "is_primary", type: "boolean", example: true),
                                new OA\Property(property: "inclusion_group", type: "string", example: "gender", nullable: true),
                                new OA\Property(property: "inclusion_type", type: "string", example: "girls", nullable: true),
                                new OA\Property(property: "source", type: "string", enum: ["ai_confirmed", "ai_modified", "human_entered"], example: "human_entered"),
                                new OA\Property(
                                    property: "education_level_ids",
                                    type: "array",
                                    items: new OA\Items(type: "integer"),
                                    example: [1, 2, 3]
                                ),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Activities saved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Activities saved."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ProgrammeActivity")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
            new OA\Response(
                response: 422,
                description: "Validation failed — includes rejection of inactive/deprecated taxonomy items",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreProgrammeActivityRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canWrite($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $created = [];

        foreach ($request->validated('activities') as $activityData) {
            $activity = $programmeEntry->activities()->create([
                'activity_item_id' => $activityData['activity_item_id'],
                'is_primary' => $activityData['is_primary'] ?? false,
                'inclusion_group' => $activityData['inclusion_group'] ?? null,
                'inclusion_type' => $activityData['inclusion_type'] ?? null,
                'source' => $activityData['source'] ?? 'human_entered',
            ]);

            $activity->activityLevels()->createMany(
                array_map(
                    fn($levelId) => ['education_level_id' => $levelId],
                    $activityData['education_level_ids']
                )
            );

            $created[] = $activity->load('activityLevels');
        }

        return response()->json([
            'message' => 'Activities saved.',
            'data' => $created,
        ], 201);
    }

    protected function canView(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        $user = $request->user();

        if (in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            return true;
        }

        return $programmeEntry->organisation_id === $user->organisation_id;
    }

    protected function canWrite(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        $user = $request->user();

        if ($user->role === 'nep_admin') {
            return true;
        }

        return $programmeEntry->organisation_id === $user->organisation_id;
    }
}

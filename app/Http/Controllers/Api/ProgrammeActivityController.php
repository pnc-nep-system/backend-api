<?php

namespace App\Http\Controllers\Api;

use App\Events\TaxonomyOtherQueueCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeActivityRequest;
use App\Models\ActivityItem;
use App\Models\ProgrammeActivity;
use App\Models\ProgrammeActivityLevel;
use App\Models\ProgrammeEntry;
use App\Models\TaxonomyOtherQueue;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

// Bulk insert data structure
class ActivityLevelBulkInsert
{
    public static function prepare(array $activityData): array
    {
        return array_map(
            fn($levelId) => ['education_level_id' => $levelId],
            $activityData['education_level_ids']
        );
    }
}

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
        description: "Accepts one or more activity selections, each with a taxonomy item, inclusion flag/group/type, multiple education levels, and a primary/secondary flag. Inclusion is recorded once per activity item, not per education level. Rejects inactive or deprecated taxonomy items. When a selected item is flagged \"Other\", the accompanying free-text value is required (SRS 5.5) and is captured in the taxonomy other-queue for later admin review.",
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
                                new OA\Property(property: "other_text", type: "string", example: "Community radio literacy programme", nullable: true, description: "Required when the selected activity_item_id is flagged as \"Other\" (SRS 5.5)"),
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
                description: "Validation failed — includes rejection of inactive/deprecated taxonomy items, and missing free-text when \"Other\" is selected",
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
        $itemIds = collect($request->validated('activities'))
            ->pluck('activity_item_id')
            ->unique();

        $activityItems = ActivityItem::whereIn('id', $itemIds)->get()->keyBy('id');

        // Prepare all activities and taxonomy queues for bulk insert
        $activitiesToCreate = [];
        $activityLevelsToCreate = []; // [activity_index => [levels]]
        $taxonomyQueuesToCreate = [];
        $activityIndex = 0;

        foreach ($request->validated('activities') as $activityData) {
            $activityItem = $activityItems->get($activityData['activity_item_id']);
            
            if (!$activityItem) {
                continue; // Skip if activity item not found (shouldn't happen due to validation)
            }

            $activitiesToCreate[] = [
                'programme_entry_id' => $programmeEntry->id,
                'activity_item_id' => $activityData['activity_item_id'],
                'is_primary' => $activityData['is_primary'] ?? false,
                'inclusion_group' => $activityData['inclusion_group'] ?? null,
                'inclusion_type' => $activityData['inclusion_type'] ?? null,
                'source' => $activityData['source'] ?? 'human_entered',
                'taxonomy_version' => $activityItem->version,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Store activity levels for bulk insert after activities are created
            $activityLevelsToCreate[$activityIndex] = array_map(
                fn($levelId) => ['education_level_id' => $levelId],
                $activityData['education_level_ids']
            );

            // Store taxonomy queue data if needed
            if ($activityItem->is_other) {
                $taxonomyQueuesToCreate[] = [
                    'programme_entry_id' => $programmeEntry->id,
                    'item_id' => $activityItem->id,
                    'other_text' => $activityData['other_text'],
                    'suggested_subcategory_id' => $activityItem->subcategory_id,
                    'frequency' => 1,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $activityIndex++;
        }

        // Bulk insert activities
        if (!empty($activitiesToCreate)) {
            ProgrammeActivity::insert($activitiesToCreate);
            
            // Now bulk insert activity levels for each activity
            $createdActivities = ProgrammeActivity::where('programme_entry_id', $programmeEntry->id)
                ->orderByDesc('id')
                ->limit(count($activitiesToCreate))
                ->get();
            
            $allActivityLevels = [];
            foreach ($createdActivities as $index => $activity) {
                $levels = $activityLevelsToCreate[$index] ?? [];
                foreach ($levels as $level) {
                    $allActivityLevels[] = [
                        'programme_activity_id' => $activity->id,
                        'education_level_id' => $level['education_level_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            
            if (!empty($allActivityLevels)) {
                ProgrammeActivityLevel::insert($allActivityLevels);
            }
        }

        // Bulk insert taxonomy queues
        if (!empty($taxonomyQueuesToCreate)) {
            TaxonomyOtherQueue::insert($taxonomyQueuesToCreate);
        }

        // Load the created activities with their relationships for response
        $created = $programmeEntry->activities()
            ->with('activityLevels')
            ->orderByDesc('id')
            ->limit(count($activitiesToCreate))
            ->get()
            ->reverse()
            ->values()
            ->all();

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
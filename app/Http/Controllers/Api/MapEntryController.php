<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
class MapEntryController extends Controller
{
    #[OA\Get(
        path: "/map/entries",
        summary: "Query programme entries for map display, filterable by taxonomy category/sub-category/item, education level, and inclusion group/type",
        description: "Returns entries with at least one activity matching the given category, sub-category, item, education level, inclusion group, and/or inclusion type filter. Filters combine with AND. Respects BE-010/BE-025 visibility rules: nep_admin and nep_coordinator see all entries, member_org sees only their own organisation's entries.",
        security: [["bearerAuth" => []]],
        tags: ["Map Query & Export"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, description: "Filter by taxonomy category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "subcategory_id", in: "query", required: false, description: "Filter by taxonomy sub-category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item_id", in: "query", required: false, description: "Filter by taxonomy item ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "education_level_id", in: "query", required: false, description: "Filter by education level ID (via programme_activity_levels)", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "inclusion_group", in: "query", required: false, description: "Filter by inclusion group value", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "inclusion_type", in: "query", required: false, description: "Filter by inclusion type value", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Filtered entries retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ProgrammeEntry")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ProgrammeEntry::query();
        // BE-010/BE-025 visibility: nep_admin and nep_coordinator see all; member_org sees only their own organisation's entries
        if (! in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            $query->where('organisation_id', $user->organisation_id);
        }
        if ($request->filled('category_id')) {
            $query->whereHas('activities.activityItem.subcategory', function ($q) use ($request) {
                $q->where('category_id', $request->input('category_id'));
            });
        }
        if ($request->filled('subcategory_id')) {
            $query->whereHas('activities.activityItem', function ($q) use ($request) {
                $q->where('subcategory_id', $request->input('subcategory_id'));
            });
        }
        if ($request->filled('item_id')) {
            $query->whereHas('activities', function ($q) use ($request) {
                $q->where('activity_item_id', $request->input('item_id'));
            });
        }

        // BE-027: education level — lives on the programme_activity_levels
        // pivot table (many-to-many between activities and education_levels),
        // so we filter via a subquery against that pivot rather than a
        // direct column on programme_activities.
        if ($request->filled('education_level_id')) {
            $educationLevelId = $request->input('education_level_id');
            $query->whereHas('activities', function ($q) use ($educationLevelId) {
                $q->whereExists(function ($sub) use ($educationLevelId) {
                    $sub->select(DB::raw(1))
                        ->from('programme_activity_levels')
                        ->whereColumn('programme_activity_levels.programme_activity_id', 'programme_activities.id')
                        ->where('programme_activity_levels.education_level_id', $educationLevelId);
                });
            });
        }

        // BE-027: inclusion group / inclusion type — plain columns directly
        // on programme_activities (not foreign keys, not IDs).
        if ($request->filled('inclusion_group')) {
            $query->whereHas('activities', function ($q) use ($request) {
                $q->where('inclusion_group', $request->input('inclusion_group'));
            });
        }
        if ($request->filled('inclusion_type')) {
            $query->whereHas('activities', function ($q) use ($request) {
                $q->where('inclusion_type', $request->input('inclusion_type'));
            });
        }

        return response()->json(['data' => $query->get()]);
    }
}
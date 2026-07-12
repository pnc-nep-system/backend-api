<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MapEntryController extends Controller
{
    #[OA\Get(
        path: "/map/entries",
        summary: "Query programme entries for map display, filterable by taxonomy category/sub-category/item",
        description: "Returns entries with at least one activity matching the given category, sub-category, or item filter. Respects BE-010/BE-025 visibility rules: nep_admin and nep_coordinator see all entries, member_org sees only their own organisation's entries.",
        security: [["bearerAuth" => []]],
        tags: ["Map Query & Export"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, description: "Filter by taxonomy category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "subcategory_id", in: "query", required: false, description: "Filter by taxonomy sub-category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item_id", in: "query", required: false, description: "Filter by taxonomy item ID", schema: new OA\Schema(type: "integer")),
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

        return response()->json(['data' => $query->get()]);
    }
}
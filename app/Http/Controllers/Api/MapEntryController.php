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
            new OA\Parameter(name: "province_id", in: "query", required: false, description: "Filter by province ID (matches entries with locations in this province or its districts)", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "district_id", in: "query", required: false, description: "Filter by district ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "agreement_counterpart_type", in: "query", required: false, description: "Filter by government agreement counterpart agency type", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "agreement_status", in: "query", required: false, description: "Filter by government agreement status", schema: new OA\Schema(type: "string")),
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

        // BE-030: Use distinct to prevent duplicate rows when entries match through multiple joined rows
        $query->distinct();

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

        if ($request->filled('province_id')) {
            $provinceId = $request->input('province_id');
            $query->whereHas('locations', function ($q) use ($provinceId) {
                // Match entries where the location is directly in the province OR in a district within the province
                $q->where(function ($subQ) use ($provinceId) {
                    $subQ->where('province_id', $provinceId)
                          ->orWhereHas('district', function ($districtQ) use ($provinceId) {
                              $districtQ->where('province_id', $provinceId);
                          });
                });
            });
        }

        if ($request->filled('district_id')) {
            $query->whereHas('locations', function ($q) use ($request) {
                $q->where('district_id', $request->input('district_id'));
            });
        }

        if ($request->filled('agreement_counterpart_type')) {
            $query->whereHas('governmentAgreements', function ($q) use ($request) {
                $q->where('counterpart_agency', $request->input('agreement_counterpart_type'));
            });
        }

        if ($request->filled('agreement_status')) {
            $query->whereHas('governmentAgreements', function ($q) use ($request) {
                $q->where('status', $request->input('agreement_status'));
            });
        }

        return response()->json(['data' => $query->get()]);
    }
}
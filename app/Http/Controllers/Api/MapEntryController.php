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
        summary: "Query programme entries for map display, filterable by taxonomy, keyword, organisation, and scale",
        description: "Returns entries matching the given filters. Keyword and organisation name filters use case-insensitive partial matching. Scale filters bucket by budget band, or by numeric staff/beneficiary ranges. Respects BE-010/BE-025 visibility rules.",
        security: [["bearerAuth" => []]],
        tags: ["Map Query & Export"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, description: "Filter by taxonomy category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "subcategory_id", in: "query", required: false, description: "Filter by taxonomy sub-category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item_id", in: "query", required: false, description: "Filter by taxonomy item ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "keyword", in: "query", required: false, description: "Case-insensitive partial match against entry keywords", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "organisation_name", in: "query", required: false, description: "Case-insensitive partial match against organisation name", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "budget_band_id", in: "query", required: false, description: "Filter by budget band ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "min_staff", in: "query", required: false, description: "Minimum FTE staff", schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "max_staff", in: "query", required: false, description: "Maximum FTE staff", schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "min_beneficiaries", in: "query", required: false, description: "Minimum direct beneficiaries", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "max_beneficiaries", in: "query", required: false, description: "Maximum direct beneficiaries", schema: new OA\Schema(type: "integer")),
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

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->whereHas('keywords', function ($q) use ($keyword) {
                $q->whereRaw('LOWER(keyword) LIKE ?', ['%' . strtolower($keyword) . '%']);
            });
        }

        if ($request->filled('organisation_name')) {
            $orgName = $request->input('organisation_name');
            $query->whereHas('organisation', function ($q) use ($orgName) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($orgName) . '%']);
            });
        }

        if ($request->filled('budget_band_id')) {
            $query->where('budget_band_id', $request->input('budget_band_id'));
        }

        if ($request->filled('min_staff')) {
            $query->where('fte_staff', '>=', $request->input('min_staff'));
        }
        if ($request->filled('max_staff')) {
            $query->where('fte_staff', '<=', $request->input('max_staff'));
        }

        if ($request->filled('min_beneficiaries')) {
            $query->where('direct_beneficiaries', '>=', $request->input('min_beneficiaries'));
        }
        if ($request->filled('max_beneficiaries')) {
            $query->where('direct_beneficiaries', '<=', $request->input('max_beneficiaries'));
        }

        return response()->json(['data' => $query->get()]);
    }
}
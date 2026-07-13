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
        summary: "Query programme entries for map display, filterable by taxonomy, education level, inclusion, location, agreement, keyword, organisation, and scale",
        description: "Returns entries matching the given filters. Taxonomy (category/sub-category/item), education level, and inclusion group/type filters match entries with at least one qualifying activity. Location filters match via programme_locations. Agreement filters match via government_agreements. Keyword and organisation name filters use case-insensitive partial matching. Scale filters bucket by budget band, or by numeric staff/beneficiary ranges. All filters combine with AND. Respects BE-010/BE-025 visibility rules: nep_admin and nep_coordinator see all entries, member_org sees only their own organisation's entries.",
        security: [["bearerAuth" => []]],
        tags: ["Map Query & Export"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, description: "Filter by taxonomy category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "subcategory_id", in: "query", required: false, description: "Filter by taxonomy sub-category ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item_id", in: "query", required: false, description: "Filter by taxonomy item ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "education_level_id", in: "query", required: false, description: "Filter by education level ID (via programme_activity_levels)", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "inclusion_group", in: "query", required: false, description: "Filter by inclusion group value", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "inclusion_type", in: "query", required: false, description: "Filter by inclusion type value", schema: new OA\Schema(type: "string")),
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
        $request->validate([
            'category_id' => 'sometimes|integer',
            'subcategory_id' => 'sometimes|integer',
            'item_id' => 'sometimes|integer',
            'education_level_id' => 'sometimes|integer',
            'inclusion_group' => 'sometimes|string',
            'inclusion_type' => 'sometimes|string',
            'province_id' => 'sometimes|integer',
            'district_id' => 'sometimes|integer',
            'agreement_counterpart_type' => 'sometimes|string',
            'agreement_status' => 'sometimes|string',
        ]);

        $user = $request->user();
        $query = ProgrammeEntry::query();

        // BE-010/BE-025 visibility: nep_admin and nep_coordinator see all;
        // member_org sees only their own organisation's entries.
        if (! in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            $query->where('organisation_id', $user->organisation_id);
        }

        $hasActivityFilter = collect($request->only([
            'category_id', 'subcategory_id', 'item_id',
            'education_level_id', 'inclusion_group', 'inclusion_type',
        ]))->filter(fn ($v) => filled($v))->isNotEmpty();

        // Location filters – match via programme_locations table.
        if ($request->filled('province_id')) {
            $query->whereHas('locations', function ($q) use ($request) {
                $q->where('province_id', $request->input('province_id'));
            });
        }

        if ($request->filled('district_id')) {
            $query->whereHas('locations', function ($q) use ($request) {
                if ($request->filled('province_id')) {
                    $provinceId = $request->input('province_id');
                    // Match entries where the location is directly in the province OR in a district within the province
                    $q->where(function ($subQ) use ($provinceId) {
                        $subQ->where('province_id', $provinceId)
                              ->orWhereHas('district', function ($districtQ) use ($provinceId) {
                                  $districtQ->where('province_id', $provinceId);
                              });
                    });
                }
                
                if ($request->filled('district_id')) {
                    $q->where('district_id', $request->input('district_id'));
                }
            });
        }

        // Government agreement filters – match via government_agreements table.
        if ($request->filled('agreement_counterpart_type')) {
            $query->whereHas('governmentAgreements', function ($q) use ($request) {
                if ($request->filled('agreement_counterpart_type')) {
                    $q->where('counterpart_agency', $request->input('agreement_counterpart_type'));
                }
                
                if ($request->filled('agreement_status')) {
                    $q->where('status', $request->input('agreement_status'));
                }
            });
        }

        if ($hasActivityFilter) {
            $query->whereHas('activities', function ($q) use ($request) {
                if ($request->filled('category_id')) {
                    $q->whereHas('activityItem.subcategory', function ($sub) use ($request) {
                        $sub->where('category_id', $request->input('category_id'));
                    });
                }

                if ($request->filled('subcategory_id')) {
                    $q->whereHas('activityItem', function ($sub) use ($request) {
                        $sub->where('subcategory_id', $request->input('subcategory_id'));
                    });
                }

                if ($request->filled('item_id')) {
                    $q->where('activity_item_id', $request->input('item_id'));
                }


                // BE-027: education level lives on the programme_activity_levels
                // pivot (many-to-many between activities and education_levels).
                // whereColumn scopes the EXISTS to this same activity row.
                if ($request->filled('education_level_id')) {
                    $educationLevelId = $request->input('education_level_id');
                    $q->whereExists(function ($sub) use ($educationLevelId) {
                        $sub->select(DB::raw(1))
                            ->from('programme_activity_levels')
                            ->whereColumn('programme_activity_levels.programme_activity_id', 'programme_activities.id')
                            ->where('programme_activity_levels.education_level_id', $educationLevelId);
                    });
                }

                // BE-027: inclusion group/type are plain columns directly on
                // programme_activities (not foreign keys, not IDs).
                if ($request->filled('inclusion_group')) {
                    $q->where('inclusion_group', $request->input('inclusion_group'));
                }

                if ($request->filled('inclusion_type')) {
                    $q->where('inclusion_type', $request->input('inclusion_type'));
                }
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

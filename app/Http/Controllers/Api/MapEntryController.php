<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // BE-030: Use distinct to prevent duplicate rows when entries match through multiple joined rows
        $query->distinct();

        $hasActivityFilter = collect($request->only([
            'category_id', 'subcategory_id', 'item_id',
            'education_level_id', 'inclusion_group', 'inclusion_type',
        ]))->filter(fn ($v) => filled($v))->isNotEmpty();

        // Location filters – match via programme_locations table.
        // BE-030: Combined province and district filters in single whereHas to ensure they apply to the same location record
        if ($request->filled('province_id') || $request->filled('district_id')) {
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
        // BE-030: Combined counterpart type and status filters in single whereHas to ensure they apply to the same agreement record
        if ($request->filled('agreement_counterpart_type') || $request->filled('agreement_status')) {
            $query->whereHas('governmentAgreements', function ($q) use ($request) {
                if ($request->filled('agreement_counterpart_type')) {
                    $q->where('counterpart_agency', $request->input('agreement_counterpart_type'));
                }
                
                if ($request->filled('agreement_status')) {
                    $q->where('status', $request->input('agreement_status'));
                }
            });
        }

        // Activity filters – match via programme_activities table.
        // BE-030: All activity-related filters are combined in single whereHas to ensure they apply to the same activity record
        if ($hasActivityFilter) {
            $query->whereHas('activities', function ($q) use ($request) {
                if ($request->filled('item_id')) {
                    $q->where('activity_item_id', $request->input('item_id'));
                }
                
                if ($request->filled('subcategory_id') || $request->filled('category_id')) {
                    $q->whereHas('activityItem.subcategory', function ($subQ) use ($request) {
                        if ($request->filled('subcategory_id')) {
                            $subQ->where('subcategory_id', $request->input('subcategory_id'));
                        }
                        
                        if ($request->filled('category_id')) {
                            $subQ->where('category_id', $request->input('category_id'));
                        }
                    });
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

    #[OA\Get(
        path: "/map/entries/export",
        summary: "Export filtered programme entries as CSV",
        description: "Returns a CSV file containing all key fields for entries matching the given filters. Uses the same filters and permissions as the query endpoint. CSV includes entry details, organisation info, budget, staff, beneficiaries, locations, activities, keywords, and government agreements.",
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
            new OA\Parameter(name: "province_id", in: "query", required: false, description: "Filter by province ID (matches entries with locations in this province or its districts)", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "district_id", in: "query", required: false, description: "Filter by district ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "agreement_counterpart_type", in: "query", required: false, description: "Filter by government agreement counterpart agency type", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "agreement_status", in: "query", required: false, description: "Filter by government agreement status", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "CSV export successful",
                content: new OA\MediaType(
                    mediaType: "text/csv",
                    schema: new OA\Schema(type: "string", format: "binary")
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]

    public function generateCsv(array $entries): string
    {
        $handle = fopen('php://temp', 'w+');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // CSV Headers
        fputcsv($handle, [
            'Entry ID',
            'Programme Name',
            'Organisation Name',
            'Budget Band',
            'Start Year',
            'End Year',
            'Ongoing',
            'FTE Staff',
            'Direct Beneficiaries',
            'Indirect Beneficiaries',
            'Method',
            'Verified Date',
            'Last Updated',
            'Keywords',
            'Locations (Provinces/Districts)',
            'Activities (Taxonomy)',
            'Education Levels',
            'Inclusion Groups',
            'Inclusion Types',
            'Government Agreements',
        ]);

        // CSV Data
        foreach ($entries as $entry) {
            $keywords = $entry->keywords->pluck('keyword')->implode('; ');
            $locations = $entry->locations->map(function ($loc) {
                $parts = [];
                if ($loc->province) {
                    $parts[] = $loc->province->province_name;
                }
                if ($loc->district) {
                    $parts[] = $loc->district->name;
                }
                return implode('/', $parts);
            })->filter()->unique()->implode('; ');

            $activities = $entry->activities->map(function ($activity) {
                $taxonomy = [];
                if ($activity->activityItem && $activity->activityItem->subcategory) {
                    $subcat = $activity->activityItem->subcategory;
                    if ($subcat->category) {
                        $taxonomy[] = $subcat->category->category_name;
                    }
                    $taxonomy[] = $subcat->subcategory_name;
                }
                if ($activity->activityItem) {
                    $taxonomy[] = $activity->activityItem->item_name;
                }
                return implode(' > ', array_filter($taxonomy));
            })->filter()->unique()->implode('; ');

            $educationLevels = $entry->activities->flatMap(function ($activity) {
                return $activity->activityLevels->map(function ($al) {
                    return $al->educationLevel?->level_name;
                });
            })->filter()->unique()->implode('; ');

            $inclusionGroups = $entry->activities->pluck('inclusion_group')->filter()->unique()->implode('; ');
            $inclusionTypes = $entry->activities->pluck('inclusion_type')->filter()->unique()->implode('; ');

            $agreements = $entry->governmentAgreements->map(function ($agreement) {
                return sprintf(
                    '%s (%s) - %s [%s]',
                    $agreement->counterpart_agency,
                    $agreement->institution_name,
                    $agreement->status,
                    $agreement->nature
                );
            })->implode('; ');

            fputcsv($handle, [
                $entry->id,
                $entry->programme_name,
                $entry->organisation->name ?? 'N/A',
                $entry->budgetBand->label ?? 'N/A',
                $entry->start_year,
                $entry->end_year ?? 'N/A',
                $entry->ongoing ? 'Yes' : 'No',
                $entry->fte_staff,
                $entry->direct_beneficiaries,
                $entry->indirect_beneficiaries,
                $entry->method,
                $entry->verified_date?->format('Y-m-d') ?? 'N/A',
                $entry->last_updated_at?->format('Y-m-d H:i:s') ?? 'N/A',
                $keywords ?: 'N/A',
                $locations ?: 'N/A',
                $activities ?: 'N/A',
                $educationLevels ?: 'N/A',
                $inclusionGroups ?: 'N/A',
                $inclusionTypes ?: 'N/A',
                $agreements ?: 'N/A',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    public function export(Request $request)
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
        $query = ProgrammeEntry::query()
            ->with([
                'organisation',
                'budgetBand',
                'keywords',
                'locations.province',
                'locations.district',
                'activities.activityItem.subcategory.category',
                'activities.activityItem.subcategory',
                'activities.activityItem',
                'activities.activityLevels.educationLevel',
                'governmentAgreements',
            ]);

        // BE-010/BE-025 visibility: nep_admin and nep_coordinator see all;
        // member_org sees only their own organisation's entries.
        if (! in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            $query->where('organisation_id', $user->organisation_id);
        }

        $query->distinct();

        $hasActivityFilter = collect($request->only([
            'category_id', 'subcategory_id', 'item_id',
            'education_level_id', 'inclusion_group', 'inclusion_type',
        ]))->filter(fn ($v) => filled($v))->isNotEmpty();

        // Location filters
        if ($request->filled('province_id') || $request->filled('district_id')) {
            $query->whereHas('locations', function ($q) use ($request) {
                if ($request->filled('province_id')) {
                    $provinceId = $request->input('province_id');
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

        // Government agreement filters
        if ($request->filled('agreement_counterpart_type') || $request->filled('agreement_status')) {
            $query->whereHas('governmentAgreements', function ($q) use ($request) {
                if ($request->filled('agreement_counterpart_type')) {
                    $q->where('counterpart_agency', $request->input('agreement_counterpart_type'));
                }
                
                if ($request->filled('agreement_status')) {
                    $q->where('status', $request->input('agreement_status'));
                }
            });
        }

        // Activity filters
        if ($hasActivityFilter) {
            $query->whereHas('activities', function ($q) use ($request) {
                if ($request->filled('item_id')) {
                    $q->where('activity_item_id', $request->input('item_id'));
                }
                
                if ($request->filled('subcategory_id') || $request->filled('category_id')) {
                    $q->whereHas('activityItem.subcategory', function ($subQ) use ($request) {
                        if ($request->filled('subcategory_id')) {
                            $subQ->where('subcategory_id', $request->input('subcategory_id'));
                        }
                        
                        if ($request->filled('category_id')) {
                            $subQ->where('category_id', $request->input('category_id'));
                        }
                    });
                }

                if ($request->filled('education_level_id')) {
                    $educationLevelId = $request->input('education_level_id');
                    $q->whereExists(function ($sub) use ($educationLevelId) {
                        $sub->select(DB::raw(1))
                            ->from('programme_activity_levels')
                            ->whereColumn('programme_activity_levels.programme_activity_id', 'programme_activities.id')
                            ->where('programme_activity_levels.education_level_id', $educationLevelId);
                    });
                }

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

        $entries = $query->get()->all();
        $csvContent = $this->generateCsv($entries);

        $filename = 'programme-entries-export-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeEntryRequest;
use App\Http\Requests\UpdateProgrammeEntryRequest;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Services\PdfReportCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        new OA\Property(property: "last_updated_at", type: "string", format: "date-time", nullable: true, description: "Timestamp of last update (automatically set by system)"),
        new OA\Property(property: "last_updated_by", type: "integer", nullable: true, description: "ID of user who last updated (automatically set by system, read-only)"),
        new OA\Property(property: "is_submitted", type: "boolean", example: false, description: "Whether the entry has been submitted for review"),
        new OA\Property(property: "is_unverified", type: "boolean", example: false, description: "Whether the entry is flagged as unverified (stale)"),
        new OA\Property(
            property: "locations",
            type: "array",
            nullable: true,
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "id", type: "integer"),
                    new OA\Property(property: "programme_entry_id", type: "integer"),
                    new OA\Property(
                        property: "province",
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                        ],
                        type: "object",
                        nullable: true
                    ),
                    new OA\Property(property: "country", type: "string", nullable: true),
                ],
                type: "object"
            )
        ),
        new OA\Property(
            property: "activities",
            type: "array",
            nullable: true,
            description: "Primary activities (is_primary = true)",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "id", type: "integer"),
                    new OA\Property(property: "programme_entry_id", type: "integer"),
                    new OA\Property(property: "is_primary", type: "boolean", example: true),
                    new OA\Property(
                        property: "activity_item",
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "label", type: "string"),
                            new OA\Property(property: "code", type: "string"),
                        ],
                        type: "object",
                        nullable: true

                    ),
                ],
                type: "object"
            )
        ),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class ProgrammeEntryController extends Controller
{
    public function myDrafts(Request $request)
    {
        $user = $request->user();

        if (! in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $entries = ProgrammeEntry::with(['organisation:id,name', 'activities.activityItem:id,code,label'])
            ->select('id','programme_name','organisation_id','updated_at','created_by','is_submitted','is_unverified')
            ->where('is_submitted', false)
            ->where('created_by', $user->id)
            ->orderByDesc('updated_at')
            ->paginate(50);

        return response()->json($entries->through(fn($entry) => [
            ...$entry->toArray(),
            'organisation_name' => $entry->organisation?->name,
        ]));
    }

    public function getAll(Request $request)
    {
        return $this->entriesByStatus($request, null);
    }

    #[OA\Post(
        path: "/programme-entries",
        summary: "Create a new programme entry",
        description: "Creates a Section 1 programme entry. member_org users create entries for their own organisation as usual (organisation_id is automatically assigned). NEP Admin and NEP Coordinator can additionally create an entry on behalf of a member organisation — for example, to assist an organisation that needs help using the system — by specifying organisation_id explicitly.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["programme_name", "start_year"],
                properties: [
                    new OA\Property(
                        property: "organisation_id",
                        type: "integer",
                        example: 1,
                        nullable: true,
                        description: "Only used by NEP Admin/Coordinator when creating an entry on behalf of a member organisation. member_org users should omit this field — it is auto-assigned to their own organisation and will be rejected if sent."
                    ),
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
                description: "Validation failed — organisation_id is required for NEP Admin/Coordinator, and prohibited for member_org",
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
        $user = $request->user();
        $validated = $request->validated();


        if (! in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            $validated['organisation_id'] = $user->organisation_id;
        }

        // Admin/coordinator always create as draft — the org reviews and submits
        if (in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            $validated['is_submitted'] = false;
        }

        $entry = ProgrammeEntry::create($validated);

        // Notify the org's users when admin/coordinator creates a programme on their behalf
        if (in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            $orgUsers = \App\Models\User::where('organisation_id', $entry->organisation_id)
                ->where('status', 'active')
                ->get();
            foreach ($orgUsers as $orgUser) {
                $orgUser->notify(new \App\Notifications\ProgrammeEntryCreatedForOrg($entry));
            }
        }

        return response()->json([
            'message' => 'Programme entry created.',
            'data' => $entry,
        ], 201);
    }

    #[OA\Put(
        path: "/programme-entries/{programmeEntry}",
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
        $wasSubmitted = $programmeEntry->is_submitted;
        $user = $request->user();
        $validated = $request->validated();

        // Admin/coordinator cannot submit on behalf of org — force draft
        if (in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            $validated['is_submitted'] = false;
        }

        $programmeEntry->update($validated);


        return response()->json([
            'message' => 'Programme entry updated.',
            'data' => $programmeEntry->fresh(),
        ]);
    }
    #[OA\Get(
        path: "/organisations/{organisation}/programme-entries",
        summary: "List programme entries for an organisation",
        description: "Returns all programme entries belonging to the given organisation. Returns 404 (not 403) if the caller is not authorized to view that organisation's entries, per member-org scoping.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "organisation",
                in: "path",
                required: true,
                description: "Organisation ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of programme entries",
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
            new OA\Response(response: 404, description: "Organisation not found, or caller not authorized to view it"),
        ]
    )]

    public function index(Request $request, Organisation $organisation)
    {
        $user = $request->user();
        if (! in_array($user->role, ['nep_admin', 'nep_coordinator']) && $organisation->id !== $user->organisation_id) {
            return response()->json(['message' => 'Not Found.'], 404);
        }
        $entries = ProgrammeEntry::with(['organisation:id,name', 'activities.activityItem:id,code,label'])
            ->where('organisation_id', $organisation->id)
            ->get();
        return response()->json(['data' => $entries]);
    }

    #[OA\Get(
        path: "/programme-entries/{programmeEntry}",
        summary: "Get a single programme entry",
        description: "Retrieves one programme entry. Returns 404 if the entry does not exist or the caller is not authorized to view it, per member-org scoping.",
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
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(
                response: 404,
                description: "Entry not found, or caller not authorized to view it",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "Not Found.")]
                )
            ),
        ]
    )]

    public function show(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canManage($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }


        $programmeEntry->load([
            'organisation',
            'budgetBand',
            'keywords',
            'locations.province',
            'locations.district',
            'locations.commune',
            'locations.village',
            'activities.activityItem.subcategory.category',
            'activities.activityLevels.educationLevel',
            'governmentAgreements',
        ]);

        return response()->json(['data' => $programmeEntry]);
    }

    #[OA\Get(
        path: "/programme-entries/draft",
        summary: "List draft (unsubmitted) programme entries",
        description: "Returns paginated draft programme entries. Restricted to member_org role — NEP Admin and NEP Coordinator are forbidden, since draft/submission status is a member-organisation workflow concept that doesn't apply to review roles. member_org users see only their own organisation's drafts.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "page",
                in: "query",
                required: false,
                description: "Page number for pagination",
                schema: new OA\Schema(type: "integer", default: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of draft entries",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ProgrammeEntry")
                        ),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 10),
                        new OA\Property(property: "total", type: "integer", example: 42),
                        new OA\Property(property: "last_page", type: "integer", example: 5),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(
                response: 403,
                description: "Forbidden — NEP Admin and NEP Coordinator cannot access this endpoint",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "Forbidden.")]
                )
            ),
        ]
    )]
    public function draft(Request $request)
    {
        $user = $request->user();

        if (in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            return $this->myDrafts($request);
        }

        return $this->entriesByStatus($request, false);
    }


    #[OA\Get(
        path: "/programme-entries/submitted",
        summary: "List submitted programme entries",
        description: "Returns paginated submitted programme entries. Restricted to member_org role — NEP Admin and NEP Coordinator are forbidden, since draft/submission status is a member-organisation workflow concept. member_org users see only their own organisation's submitted entries.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "page",
                in: "query",
                required: false,
                description: "Page number for pagination",
                schema: new OA\Schema(type: "integer", default: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of submitted entries",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ProgrammeEntry")
                        ),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 10),
                        new OA\Property(property: "total", type: "integer", example: 42),
                        new OA\Property(property: "last_page", type: "integer", example: 5),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(
                response: 403,
                description: "Forbidden — NEP Admin and NEP Coordinator cannot access this endpoint",
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "Forbidden.")]
                )
            ),
        ]
    )]

    public function submitted(Request $request)
    {
        return $this->entriesByStatus($request, true);
    }

    private function entriesByStatus(Request $request, ?bool $isSubmitted)
    {
        $user = $request->user();
        $query = ProgrammeEntry::with([
            'organisation:id,name',
            'locations.province:id,province_name',
            'locations.district:id,name',
            'activities.activityItem:id,code,label',
        ])->select(
            'id','programme_name','organisation_id','budget_band_id',
            'start_year','end_year','ongoing','is_submitted','is_unverified',
            'updated_at','created_at'
        )->orderByDesc('id');

        if ($isSubmitted !== null) {
            $query->whereRaw('is_submitted = ?', [(int) $isSubmitted]);
        }

        if ($user->role === 'member_org') {
            $query->whereRaw('organisation_id = ?', [(int) $user->organisation_id]);
        }

        return response()->json($query->paginate(10)->through(fn($entry) => [
            ...$entry->toArray(),
            'organisation_name' => $entry->organisation?->name,
        ]));
    }


    #[OA\Patch(
        path: "/programme-entries/{programmeEntry}/verify",
        summary: "Mark a programme entry as verified (NEP Admin only)",
        description: "Clears the unverified flag and records the review date. Restricted to NEP Administrator role per SRS 8.2 annual review workflow.",
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
                description: "Entry verified successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Entry verified."),
                        new OA\Property(property: "data", ref: "#/components/schemas/ProgrammeEntry"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Only NEP Admin may verify entries"),
            new OA\Response(response: 404, description: "Programme entry not found"),
        ]
    )]
    public function verify(Request $request, ProgrammeEntry $programmeEntry)
    {
        if ($request->user()->role !== 'nep_admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $programmeEntry->verified_date = now();
        $programmeEntry->save(); // saving() hook automatically sets is_unverified = false

        return response()->json([
            'message' => 'Entry verified.',
            'data' => $programmeEntry->fresh(),
        ]);
    }

    public function exportPdf(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (!$this->canManage($request, $programmeEntry)) {
            return response()->json(['message' => 'Forbidden. You do not have permission to access reports for this programme.'], 403);
        }

        $entryId = $programmeEntry->id;

        // Fingerprint the underlying data so the cached PDF is automatically
        // regenerated whenever the entry, its organisation, or any related row
        // (activities, geography, keywords, agreements) changes. Taxonomy /
        // budget-band renames are intentionally excluded — staleness is bounded
        // by the daily date segment of the cache key.
        $state = DB::table('programme_entries as pe')
            ->leftJoin('organisations as org', 'org.id', '=', 'pe.organisation_id')
            ->where('pe.id', $entryId)
            ->selectRaw(
                'pe.updated_at as m0, org.updated_at as mo,'
                . ' (SELECT MAX(updated_at) FROM programme_activities WHERE programme_entry_id = pe.id) as m1,'
                . ' (SELECT MAX(updated_at) FROM programme_geography WHERE programme_entry_id = pe.id) as m2,'
                . ' (SELECT MAX(updated_at) FROM entry_keywords WHERE programme_entry_id = pe.id) as m3,'
                . ' (SELECT MAX(updated_at) FROM government_agreements WHERE programme_entry_id = pe.id) as m4'
            )
            ->first();

        $fingerprint = implode('|', [
            $state->m0 ?? 0,
            $state->mo ?? '',
            $state->m1 ?? '',
            $state->m2 ?? '',
            $state->m3 ?? '',
            $state->m4 ?? '',
        ]);

        $cacheKey = PdfReportCache::key('programme-entry:' . $entryId, $fingerprint);
        $filename = 'programme-report-' . $entryId . '-' . now()->format('Y-m-d') . '.pdf';

        return PdfReportCache::respond($cacheKey, $filename, function () use ($programmeEntry) {
            // Eager loads + DomPDF rendering only run on a cache miss.
            $programmeEntry->load([
                'organisation',
                'budgetBand',
                'activities.activityItem.subcategory.category',
                'activities.activityLevels.educationLevel',
                'locations.province',
                'locations.district',
                'governmentAgreements',
                'keywords',
            ]);

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.programme-entry-report-pdf', [
                'entry' => $programmeEntry,
            ]);
        }, str_contains($request->header('Accept-Encoding', ''), 'gzip'));
    }

    public function exportOrganisationProgrammesPdf(Request $request, Organisation $organisation)
    {
        $user = $request->user();
        if ($user->role === 'member_org' && (int)$user->organisation_id !== (int)$organisation->id) {
            return response()->json(['message' => 'Forbidden. You do not have permission to export programmes for this organisation.'], 403);
        }

        // Cheap fingerprint: aggregates over the submitted entries and their
        // related rows. Runs in a few milliseconds, yet it changes whenever
        // anything that appears in the report changes, so a stale cached PDF
        // is never served.
        $state = DB::table('programme_entries as pe')
            ->leftJoin('programme_activities as pa', 'pa.programme_entry_id', '=', 'pe.id')
            ->leftJoin('programme_geography as pl', 'pl.programme_entry_id', '=', 'pe.id')
            ->leftJoin('entry_keywords as ek', 'ek.programme_entry_id', '=', 'pe.id')
            ->leftJoin('government_agreements as ga', 'ga.programme_entry_id', '=', 'pe.id')
            ->where('pe.organisation_id', $organisation->id)
            ->where('pe.is_submitted', true)
            ->selectRaw('COUNT(DISTINCT pe.id) as c, MAX(pe.updated_at) as m0, MAX(pa.updated_at) as m1, MAX(pl.updated_at) as m2, MAX(ek.updated_at) as m3, MAX(ga.updated_at) as m4')
            ->first();

        $fingerprint = implode('|', [
            $state->c ?? 0,
            $state->m0 ?? '',
            $state->m1 ?? '',
            $state->m2 ?? '',
            $state->m3 ?? '',
            $state->m4 ?? '',
            $organisation->updated_at?->timestamp ?? '',
        ]);

        $cacheKey = PdfReportCache::key('org-programmes:' . $organisation->id, $fingerprint);
        $filename = 'organisation-programmes-' . $organisation->id . '-' . now()->format('Y-m-d') . '.pdf';

        return PdfReportCache::respond($cacheKey, $filename, function () use ($organisation) {
            // The heavy query + DomPDF render only run on a cache miss.
            $programmeEntries = ProgrammeEntry::where('organisation_id', $organisation->id)
                ->where('is_submitted', true)
                ->with([
                    'organisation',
                    'budgetBand',
                    'activities.activityItem.subcategory.category',
                    'activities.activityLevels.educationLevel',
                    'locations.province',
                    'locations.district',
                    'governmentAgreements',
                    'keywords',
                ])
                ->get();

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.organisation-programmes-report-pdf', [
                'organisation' => $organisation,
                'entries' => $programmeEntries,
            ]);
        }, str_contains($request->header('Accept-Encoding', ''), 'gzip'));
    }

    protected function canManage(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        $user = $request->user();
        return in_array($user->role, ['nep_admin', 'nep_coordinator'])
            || $programmeEntry->organisation_id === $user->organisation_id;
    }
}

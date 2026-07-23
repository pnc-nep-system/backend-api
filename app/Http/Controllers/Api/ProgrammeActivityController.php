<?php

namespace App\Http\Controllers\Api;

use App\Events\TaxonomyOtherQueueCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeActivityRequest;
use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ProgrammeActivity;
use App\Support\ActivityLevelBulkInsert;
use App\Models\ProgrammeActivityLevel;
use App\Models\ProgrammeEntry;
use App\Models\TaxonomyOtherQueue;
use App\Services\AI\GroqService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;
use Smalot\PdfParser\Parser as PdfParser;

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


    #[OA\Get(
        path: "/programme-entries/{programmeEntry}/activities",
        summary: "Get Section 2 activities for a programme entry",
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
        responses: [
            new OA\Response(
                response: 200,
                description: "List of activities for programme entry",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ProgrammeActivity")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
        ]
    )]
    public function index(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canView($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $activities = $programmeEntry->activities()
            ->with(['activityItem', 'activityLevels.educationLevel'])
            ->get();

        return response()->json([
            'data' => $activities,
        ]);
    }


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

        $activitiesData = $request->validated('activities') ?? [];
        $itemIds = collect($activitiesData)->pluck('activity_item_id')->unique();
        $activityItems = ActivityItem::whereIn('id', $itemIds)->get()->keyBy('id');

        \Illuminate\Support\Facades\DB::transaction(function () use ($programmeEntry, $activitiesData, $activityItems) {
            // Delete existing activities & activity levels for this entry to ensure clean sync
            $existingActivityIds = ProgrammeActivity::where('programme_entry_id', $programmeEntry->id)->pluck('id');
            if ($existingActivityIds->isNotEmpty()) {
                ProgrammeActivityLevel::whereIn('programme_activity_id', $existingActivityIds)->delete();
                ProgrammeActivity::whereIn('id', $existingActivityIds)->delete();
            }

            foreach ($activitiesData as $activityData) {
                $activityItem = $activityItems->get($activityData['activity_item_id']);
                if (!$activityItem) {
                    continue;
                }

                $activity = ProgrammeActivity::create([
                    'programme_entry_id' => $programmeEntry->id,
                    'activity_item_id' => $activityData['activity_item_id'],
                    'is_primary' => !empty($activityData['is_primary']),
                    'inclusion_group' => $activityData['inclusion_group'] ?? null,
                    'inclusion_type' => $activityData['inclusion_type'] ?? null,
                    'source' => $activityData['source'] ?? 'human_entered',
                    'taxonomy_version' => $activityItem->version,
                ]);

                if (!empty($activityData['education_level_ids']) && is_array($activityData['education_level_ids'])) {
                    $levels = array_map(fn($levelId) => [
                        'programme_activity_id' => $activity->id,
                        'education_level_id' => $levelId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $activityData['education_level_ids']);

                    ProgrammeActivityLevel::insert($levels);
                }

                if ($activityItem->is_other && !empty($activityData['other_text'])) {
                    TaxonomyOtherQueue::create([
                        'programme_entry_id' => $programmeEntry->id,
                        'item_id' => $activityItem->id,
                        'other_text' => $activityData['other_text'],
                        'suggested_subcategory_id' => $activityItem->subcategory_id,
                        'frequency' => 1,
                        'status' => 'pending',
                    ]);
                }
            }
        });

        // Eager load activities with activityItem relationship so code is available
        $created = $programmeEntry->activities()
            ->with(['activityItem', 'activityLevels'])
            ->get();

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

        if (in_array($user->role, ['nep_admin', 'nep_coordinator'])) {
            return true;
        }

        return $programmeEntry->organisation_id === $user->organisation_id;
    }

    public function aiAutofill(Request $request)
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:200000',
            'file' => 'nullable|file|mimes:pdf,txt|max:10240',
        ]);

        $description = trim((string) ($validated['text'] ?? ''));

        if ($request->hasFile('file')) {
            $fileText = $this->extractProgrammeDescriptionFromFile($request->file('file'));
            $description = $description !== '' ? $description . "\n\n" . $fileText : $fileText;
        }

        if (mb_strlen($description) < 10) {
            return response()->json(['message' => 'No readable programme description provided.'], 422);
        }

        // Build taxonomy list
        $categories = ActivityCategory::with(['subcategories.items' => function ($q) {
            $q->where('is_active', true)->where('is_other', false);
        }])->get();
        $taxonomyLines = [];
        foreach ($categories as $cat) {
            foreach ($cat->subcategories as $sub) {
                foreach ($sub->items as $item) {
                    $taxonomyLines[] = "{$item->code}: {$item->label} (subcategory: {$sub->label}, category: {$cat->label})";
                }
            }
        }
        $taxonomyList = implode("\n", $taxonomyLines);

        // Build provinces list
        $provinces = \App\Models\Province::select('id', 'province_name')->get();
        $provinceLines = $provinces->map(fn($p) => "{$p->id}: {$p->province_name}")->implode("\n");

        $inclusionGroups = ['Disability', 'Gender', 'LGBTIQ+', 'Ethnicity/language', 'Displacement', 'Migrant families', 'Statelessness', 'Other'];
        $counterparts = ['MoEYS national level', 'Provincial Office of Education', 'District Office of Education', 'Teacher Education Institution', 'specific school or cluster', 'other government ministry'];
        $natures = ['MoU', 'Letter of Understanding', 'official approval letter', 'informal working arrangement'];
        $statuses = ['active', 'expired', 'under renewal', 'under negotiation'];
        $budgetBands = ['Under $50,000', '$50,000–$200,000', '$200,000–$500,000', '$500,000–$2,000,000', 'Above $2,000,000'];

        $prompt = <<<PROMPT
You are an expert in the National Education Policy (NEP) for Cambodia. Analyse the programme description and return a single JSON object with 5 keys: "identity", "activities", "geography", "agreements", "keywords".

--- TAXONOMY ITEMS (CODE: Label) ---
{$taxonomyList}

--- EDUCATION LEVELS ---
1=Pre-primary/ECCD, 2=Primary, 3=Lower secondary, 4=Upper secondary, 5=Higher education

--- INCLUSION GROUPS ---
{$this->implodeArray($inclusionGroups)}
Type A=Inclusive design, Type B=Targeted programme

--- CAMBODIA PROVINCES (ID: Name) ---
{$provinceLines}

--- GOVERNMENT COUNTERPART AGENCIES (use exact values) ---
{$this->implodeArray($counterparts)}

--- AGREEMENT NATURES (use exact values) ---
{$this->implodeArray($natures)}

--- AGREEMENT STATUSES (use exact values) ---
{$this->implodeArray($statuses)}

--- BUDGET BANDS (use exact value) ---
{$this->implodeArray($budgetBands)}

Return ONLY this JSON structure, no explanation:
{
  "identity": {
    "name": "Girls Education Scholarship Programme",
    "start_year": 2019,
    "end_year": null,
    "is_ongoing": true,
    "fte_staff": 12,
    "budget_band": "\$200,000–\$500,000",
    "direct_beneficiaries": 500,
    "indirect_beneficiaries": 2000
  },
  "activities": {
    "codes": ["B1.1.01"],
    "suggestions": {
      "B1.1.01": {
        "education_levels": [2, 3],
        "inclusion": {"hasInclusion": true, "dimensions": [{"group": "Gender", "type": "B"}]}
      }
    }
  },
  "geography": {
    "province_ids": [15, 21]
  },
  "agreements": [
    {
      "counterpart_agency": "MoEYS national level",
      "nature": "MoU",
      "status": "active",
      "institution_name": "Ministry of Education, Youth and Sports"
    },
    {
      "counterpart_agency": "other government ministry",
      "nature": "informal working arrangement",
      "status": "active",
      "institution_name": "Department of Social Affairs, Veterans and Youth Rehabilitation"
    }
  ],
  "keywords": ["scholarship", "girls education", "rural", "mentoring", "lower secondary"]
}

Rules:
- identity: extract programme name, years, staff count, budget band (exact value from list), beneficiary numbers — use null for any field not mentioned
- activities.codes: max 8 matching taxonomy codes
- geography.province_ids: only IDs from the provinces list above, only if clearly mentioned
- agreements: extract ALL government partnerships/agreements mentioned; use exact counterpart_agency/nature/status values from the lists above; institution_name is the specific name of the agency/department — use empty string if not specified; if no agreements mentioned return []
- keywords: 3-5 short descriptive keywords/phrases from the description
- If a section has no relevant data, return empty array/object for it

Programme description:
{$description}
PROMPT;

        try {
            $groq = App::make(GroqService::class);
            $response = $groq->generateContent($prompt, [
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = $this->parseAutofillResponse($response);

            // Validate activity codes
            $codes = collect($result['activities']['codes'] ?? [])
                ->filter(fn($c) => is_scalar($c))
                ->flatMap(fn($c) => $this->taxonomyCodeCandidates((string) $c))
                ->unique()->values();

            $validCodeSet = ActivityItem::whereIn('code', $codes->all())
                ->where('is_active', true)->pluck('code')->flip();

            $result['activities']['codes'] = $codes->filter(fn($c) => isset($validCodeSet[$c]))->values()->all();
            $result['activities']['suggestions'] = array_filter(
                $result['activities']['suggestions'] ?? [],
                fn($k) => isset($validCodeSet[$k]),
                ARRAY_FILTER_USE_KEY
            );

            // Validate province IDs
            $validProvinceIds = $provinces->pluck('id')->flip();
            $result['geography']['province_ids'] = array_values(array_filter(
                $result['geography']['province_ids'] ?? [],
                fn($id) => isset($validProvinceIds[$id])
            ));

            // Normalize agreement field values to exact canonical strings (case-insensitive match)
            $counterpartsLower = array_combine(array_map('strtolower', $counterparts), $counterparts);
            $naturesLower      = array_combine(array_map('strtolower', $natures), $natures);
            $statusesLower     = array_combine(array_map('strtolower', $statuses), $statuses);

            $result['agreements'] = array_values(array_filter(
                array_map(function ($a) use ($counterpartsLower, $naturesLower, $statusesLower) {
                    if (!is_array($a)) return null;
                    $agency  = $counterpartsLower[strtolower(trim((string) ($a['counterpart_agency'] ?? '')))] ?? null;
                    $nature  = $naturesLower[strtolower(trim((string) ($a['nature'] ?? '')))]              ?? null;
                    $status  = $statusesLower[strtolower(trim((string) ($a['status'] ?? '')))]             ?? null;
                    if (!$agency || !$nature || !$status) return null;
                    return [
                        'counterpart_agency' => $agency,
                        'nature'             => $nature,
                        'status'             => $status,
                        'institution_name'   => trim((string) ($a['institution_name'] ?? '')),
                    ];
                }, $result['agreements'] ?? []),
                fn($a) => $a !== null
            ));

            return response()->json($result);
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            $status = in_array($code, [429, 500, 503]) ? $code : 503;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    private function implodeArray(array $arr): string
    {
        return implode(', ', $arr);
    }

    private function parseAutofillResponse(array $response): array
    {
        $defaults = [
            'identity'   => [],
            'activities' => ['codes' => [], 'suggestions' => []],
            'geography'  => ['province_ids' => []],
            'agreements' => [],
            'keywords'   => [],
        ];

        // Direct structured response
        $data = null;
        if (isset($response['activities']) || isset($response['geography']) || isset($response['keywords']) || isset($response['identity'])) {
            $data = $response;
        } elseif (isset($response['executive_summary'])) {
            $data = json_decode($response['executive_summary'], true);
        }

        if (!is_array($data)) {
            return $defaults;
        }

        return array_merge($defaults, [
            'identity'   => is_array($data['identity'] ?? null) ? $data['identity'] : [],
            'activities' => [
                'codes' => is_array($data['activities']['codes'] ?? null) ? $data['activities']['codes'] : [],
                'suggestions' => is_array($data['activities']['suggestions'] ?? null) ? $data['activities']['suggestions'] : [],
            ],
            'geography' => [
                'province_ids' => is_array($data['geography']['province_ids'] ?? null) ? array_map('intval', $data['geography']['province_ids']) : [],
            ],
            'agreements' => is_array($data['agreements'] ?? null) ? $data['agreements'] : [],
            'keywords' => is_array($data['keywords'] ?? null) ? array_slice($data['keywords'], 0, 5) : [],
        ]);
    }

    public function fetchUrl(Request $request)
    {
        $request->validate(['url' => 'required|url|max:2048']);
        $url = $request->input('url');

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NEP-Bot/1.0)'])
                ->get($url);

            if (!$response->successful()) {
                return response()->json(['message' => 'Could not fetch the URL. The website may be unavailable or blocking requests.'], 422);
            }

            $html = $response->body();

            // Strip scripts, styles, nav, footer, header, noscript tags and their contents
            $html = preg_replace('/<(script|style|nav|footer|header|aside|noscript)[^>]*>.*?<\/\1>/si', '', $html);
            // Strip all remaining HTML tags
            $text = strip_tags($html);
            // Decode HTML entities
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Collapse whitespace
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
            // Limit to 15000 chars to keep prompt size reasonable
            $text = mb_substr($text, 0, 15000);

            if (mb_strlen($text) < 50) {
                return response()->json(['message' => 'Could not extract readable text from this URL. Try pasting the content manually.'], 422);
            }

            return response()->json(['text' => $text]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return response()->json(['message' => 'Could not connect to the URL. Please check the address and try again.'], 422);
        }
    }

    public function suggestActivities(Request $request)
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:200000',
            'file' => 'nullable|file|mimes:pdf,txt|max:10240',
        ]);

        $description = trim((string) ($validated['text'] ?? ''));

        if ($description === '' && $request->hasFile('file')) {
            $description = $this->extractProgrammeDescriptionFromFile($request->file('file'));
        }

        if ($this->looksLikeRawPdfContent($description)) {
            return response()->json([
                'message' => 'The uploaded PDF was sent as raw PDF data, not readable programme text. Extract the PDF text first, use OCR for scanned PDFs, or paste the programme description.',
                'errors' => [
                    'text' => ['Raw PDF data cannot be used for AI activity suggestions.'],
                ],
            ], 422);
        }

        if (mb_strlen($description) < 10) {
            return response()->json([
                'message' => 'No readable programme description was found. Please paste the programme description text, or upload a text-based PDF. Scanned/image PDFs need OCR before AI suggestions can be generated.',
                'errors' => [
                    'text' => ['The programme description must contain at least 10 readable characters.'],
                ],
            ], 422);
        }

        // Build a flat taxonomy list for the prompt
        $categories = ActivityCategory::with(['subcategories.items' => function ($q) {
            $q->where('is_active', true)->where('is_other', false);
        }])->get();

        $taxonomyLines = [];
        foreach ($categories as $cat) {
            foreach ($cat->subcategories as $sub) {
                foreach ($sub->items as $item) {
                    $taxonomyLines[] = "{$item->code}: {$item->label} (subcategory: {$sub->label}, category: {$cat->label})";
                }
            }
        }
        $taxonomyList = implode("\n", $taxonomyLines);

        $inclusionGroups = ['Disability', 'Gender', 'LGBTIQ+', 'Ethnicity/language', 'Displacement', 'Migrant families', 'Statelessness', 'Other'];
        $groupsList = implode(', ', $inclusionGroups);

        $prompt = <<<PROMPT
You are an expert in the National Education Policy (NEP) taxonomy for Cambodia.

Below is a list of all available activity taxonomy items in the format CODE: Label.

{$taxonomyList}

Education level IDs:
1 = Pre-primary / ECCD
2 = Primary
3 = Lower secondary
4 = Upper secondary
5 = Higher education

Inclusion groups: {$groupsList}
Type A = Inclusive design (the activity is designed to be inclusive for this group)
Type B = Targeted programme (the activity specifically targets this group)

Based on the following programme description, return a JSON object with:
- "codes": array of matching activity codes (max 8)
- "suggestions": object keyed by code, each with:
  - "education_levels": array of education level IDs (integers) that apply
  - "inclusion": object with "hasInclusion" (boolean) and "dimensions" (array of {"group": string, "type": "A" or "B"}) — only include if the description clearly mentions a specific target group

Return ONLY valid JSON, no explanation. Example:
{"codes":["B1.1.01","B2.3.02"],"suggestions":{"B1.1.01":{"education_levels":[2,3],"inclusion":{"hasInclusion":true,"dimensions":[{"group":"Gender","type":"B"}]}},"B2.3.02":{"education_levels":[1],"inclusion":{"hasInclusion":false,"dimensions":[]}}}}

Programme description:
{$description}
PROMPT;

        try {
            $groq = App::make(GroqService::class);
            $response = $groq->generateContent($prompt, [
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            [$codes, $suggestions] = $this->extractSuggestedCodesAndSuggestions($response);
            $codeCandidates = collect($codes)
                ->filter(fn ($code) => is_scalar($code))
                ->flatMap(fn ($code) => $this->taxonomyCodeCandidates((string) $code))
                ->unique()
                ->values();

            // Validate codes exist in taxonomy
            $validCodeSet = ActivityItem::whereIn('code', $codeCandidates->all())
                ->where('is_active', true)
                ->pluck('code')
                ->flip();

            $validCodes = $codeCandidates
                ->filter(fn ($code) => isset($validCodeSet[$code]))
                ->values()
                ->all();

            // Filter suggestions to only valid codes
            $validSuggestions = array_filter(
                $suggestions,
                fn ($key) => isset($validCodeSet[$key]),
                ARRAY_FILTER_USE_KEY
            );

            return response()->json(['data' => $validCodes, 'suggestions' => $validSuggestions]);
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            $status = in_array($code, [429, 500, 503]) ? $code : 503;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    private function extractSuggestedCodesAndSuggestions(array $response): array
    {
        // Direct structured format: {codes: [...], suggestions: {...}}
        if (!empty($response['codes']) && is_array($response['codes'])) {
            return [
                $response['codes'],
                is_array($response['suggestions'] ?? null) ? $response['suggestions'] : [],
            ];
        }

        // Try to parse from executive_summary or raw text fallback
        $raw = $response['executive_summary'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                if (!empty($decoded['codes'])) {
                    return [
                        $decoded['codes'],
                        is_array($decoded['suggestions'] ?? null) ? $decoded['suggestions'] : [],
                    ];
                }
                if (array_is_list($decoded)) {
                    return [$decoded, []];
                }
            }

            if (preg_match('/\[.*?\]/s', $raw, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (is_array($decoded)) {
                    return [$decoded, []];
                }
            }
        }

        // Plain array fallback (_raw_array from old Groq format)
        if (!empty($response['_raw_array']) && is_array($response['_raw_array'])) {
            return [$response['_raw_array'], []];
        }

        return [[], []];
    }

    private function taxonomyCodeCandidates(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return [];
        }

        $candidates = [$code];

        if (preg_match('/^([A-Z]\d+)\.(\d+)\.(\d+)$/', $code, $matches)) {
            $candidates[] = sprintf('%s.%s.%02d', $matches[1], $matches[2], (int) $matches[3]);
        }

        return $candidates;
    }

    private function extractProgrammeDescriptionFromFile(UploadedFile $file): string
    {
        return match ($file->getClientOriginalExtension()) {
            'txt' => trim((string) file_get_contents($file->getRealPath())),
            'pdf' => $this->extractTextFromPdf($file->getRealPath()),
            default => '',
        };
    }

    private function looksLikeRawPdfContent(string $text): bool
    {
        $trimmed = ltrim($text);

        return str_starts_with($trimmed, '%PDF-')
            || str_contains($trimmed, '%PDF-')
            || (str_contains($trimmed, '/FlateDecode') && str_contains($trimmed, 'endstream'))
            || (str_contains($trimmed, 'endobj') && str_contains($trimmed, 'startxref'))
            || str_contains($trimmed, '%%EOF');
    }

    private function extractTextFromPdf(string $path): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            $parsedText = trim(preg_replace('/\s+/', ' ', $pdf->getText()) ?? '');

            if ($parsedText !== '') {
                return $parsedText;
            }
        } catch (\Throwable) {
            // Fall back to the simple stream scanner below for malformed PDFs.
        }

        $contents = (string) file_get_contents($path);
        $text = '';

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contents, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = @gzuncompress($stream);
                $text .= ' ' . $this->extractTextFromPdfStream($decoded !== false ? $decoded : $stream);
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function extractTextFromPdfStream(string $stream): string
    {
        $text = '';

        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*T[Jj]/', $stream, $matches)) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\(((?:\\\\.|[^\\\\()])*)\)\s*T[Jj]/', $match, $textMatch)) {
                    $text .= ' ' . stripcslashes($textMatch[1]);
                }
            }
        }

        return $text;
    }
}

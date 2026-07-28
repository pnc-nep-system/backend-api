<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryNote;
use App\Services\Adviser\MapOverlapMatcher;
use App\Services\AI\GroqService;
use App\Services\AI\PromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

class AdviserAnalysisController extends Controller
{
    public function generateAdvisoryNote(
        int $id,
        Request $request,
        MapOverlapMatcher $matcher,
        PromptBuilder $promptBuilder
    ): JsonResponse {
        $submission = AdvisoryNote::findOrFail($id);

        $user = $request->user();
        if (!$user->isNepAdmin() && $user->role !== 'nep_coordinator') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $programmeProfile    = $request->input('programme_profile', []);
        $documentText        = $request->input('document_text');
        $analysisScope       = $submission->analysis_scope ?? 'full map';
        $analysisScopeDetail = $submission->analysis_scope_detail;

        $safeProfile = [
            'activities' => is_array($programmeProfile['activities'] ?? null) ? $programmeProfile['activities'] : [],
            'geography'  => is_array($programmeProfile['geography']  ?? null) ? $programmeProfile['geography']  : [],
            'audiences'  => is_array($programmeProfile['audiences']  ?? null) ? $programmeProfile['audiences']  : [],
        ];

        $hasActivitySignals  = !empty($safeProfile['activities']['item_ids'])
            || !empty($safeProfile['activities']['category_ids'])
            || !empty($safeProfile['activities']['subcategory_ids']);
        $hasGeographySignals = !empty($safeProfile['geography']['province_ids'])
            || !empty($safeProfile['geography']['district_ids'])
            || !empty($safeProfile['geography']['commune_ids']);

        // Fall back to the linked programme entry's data when no profile signals are provided
        if ((!$hasActivitySignals && !$hasGeographySignals) && $submission->programme_entry_id) {
            $entry = \App\Models\ProgrammeEntry::with([
                'activities.activityItem.subcategory',
                'locations',
            ])->find($submission->programme_entry_id);

            if ($entry) {
                $safeProfile['activities']['item_ids']        = $entry->activities->pluck('activity_item_id')->filter()->unique()->values()->toArray();
                $safeProfile['activities']['subcategory_ids'] = $entry->activities->map(fn($a) => $a->activityItem?->subcategory?->id)->filter()->unique()->values()->toArray();
                $safeProfile['activities']['category_ids']    = $entry->activities->map(fn($a) => $a->activityItem?->subcategory?->category_id)->filter()->unique()->values()->toArray();
                $safeProfile['geography']['province_ids']     = $entry->locations->pluck('province_id')->filter()->unique()->values()->toArray();
                $safeProfile['geography']['district_ids']     = $entry->locations->pluck('district_id')->filter()->unique()->values()->toArray();
                $safeProfile['geography']['commune_ids']      = $entry->locations->pluck('commune_id')->filter()->unique()->values()->toArray();
            }
        }

        $overlappingEntries = $matcher
            ->match($safeProfile, $analysisScope)
            ->with([
                'organisation', 'budgetBand', 'keywords',
                'locations.province', 'locations.district', 'locations.commune', 'locations.village',
                'activities.activityItem.subcategory.category',
                'activities.activityItem.subcategory',
                'activities.activityItem',
                'activities.activityLevels.educationLevel',
            ])
            ->get();

        // Exclude the submission's own linked entry from overlaps
        if ($submission->programme_entry_id) {
            $overlappingEntries = $overlappingEntries->where('id', '!=', $submission->programme_entry_id)->values();
        }

        // Build the serialised map data BEFORE passing to the prompt builder
        $mapOverlapEntries = $overlappingEntries->map(function ($entry) {
            return [
                'id'             => $entry->id,
                'programme_name' => $entry->programme_name,
                'organisation'   => $entry->organisation?->name,
                'locations'      => $entry->locations->map(fn($l) => [
                    'province_id'   => $l->province_id,
                    'district_id'   => $l->district_id,
                    'commune_id'    => $l->commune_id,
                    'province_name' => $l->province?->province_name,
                    'district_name' => $l->district?->name,
                ])->toArray(),
                'activities'     => $entry->activities->map(fn($a) => [
                    'activity_item_id' => $a->activity_item_id,
                    'name'             => $a->activityItem?->label,
                    'subcategory_id'   => $a->activityItem?->subcategory?->id,
                    'subcategory_name' => $a->activityItem?->subcategory?->label,
                    'category_id'      => $a->activityItem?->subcategory?->category_id,
                    'category_name'    => $a->activityItem?->subcategory?->category?->label,
                ])->toArray(),
            ];
        })->values()->toArray();

        $prompt = $promptBuilder->build(
            $safeProfile,
            $mapOverlapEntries,
            $analysisScope,
            $analysisScopeDetail,
            $documentText
        );

        try {
            $groq       = App::make(GroqService::class);
            $aiResponse = $groq->generateContent($prompt);

            // Persist AI-generated sections A, C, D back to the advisory note
            $updateData = ['status' => 'analysed'];
            if (!empty($aiResponse['section_a'])) {
                $updateData['section_profile'] = $aiResponse['section_a'];
            }
            if (!empty($aiResponse['section_c'])) {
                $updateData['section_gaps'] = $aiResponse['section_c'];
            }
            if (!empty($aiResponse['section_d'])) {
                $updateData['section_coordinators_notes'] = $aiResponse['section_d'];
            }
            $submission->update($updateData);

            $aiResponse['map_overlap_entries'] = $mapOverlapEntries;

            return response()->json([
                'message' => 'Advisory note generated successfully.',
                'data'    => $aiResponse,
            ]);
        } catch (\Throwable $e) {
            $statusCode = $e->getCode();
            $httpStatus = in_array($statusCode, [400, 401, 403, 404, 429, 500, 502, 503]) ? $statusCode : 503;

            if ($httpStatus < 100 || $httpStatus > 599) {
                $httpStatus = 503;
            }

            Log::warning('Advisory note generation failed', [
                'submission_id' => $submission->id,
                'status_code'   => $statusCode,
                'user_id'       => $user->id,
                'error'         => $e->getMessage(),
            ]);

            // AI failed but we still have real DB overlap data — return it so Section B works
            return response()->json([
                'message' => 'Advisory note generated successfully.',
                'data'    => ['map_overlap_entries' => $mapOverlapEntries],
            ]);
        }
    }

    public function parsePdf(int $id, Request $request): JsonResponse
    {
        AdvisoryNote::findOrFail($id);

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $realPath = $request->file('file')->getRealPath();

        if ($realPath === false || !str_starts_with($realPath, sys_get_temp_dir())) {
            return response()->json(['message' => 'Invalid file path.'], 422);
        }

        try {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($realPath);
            $text   = $pdf->getText();

            if (empty(trim($text))) {
                return response()->json(['message' => 'Could not extract text from the PDF. The file may be scanned or image-based.'], 422);
            }

            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            Log::error('PDF parsing failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to parse PDF file.'], 422);
        }
    }
}

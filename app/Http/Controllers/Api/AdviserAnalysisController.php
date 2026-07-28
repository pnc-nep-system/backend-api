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

        $request->validate([
            'programme_profile'            => 'required|array',
            'programme_profile.activities' => 'sometimes|array',
            'programme_profile.geography'  => 'sometimes|array',
            'programme_profile.audiences'  => 'sometimes|array',
        ]);

        $programmeProfile    = $request->input('programme_profile');
        $analysisScope       = $submission->analysis_scope ?? 'full map';
        $analysisScopeDetail = $submission->analysis_scope_detail;

        $safeProfile = [
            'activities' => is_array($programmeProfile['activities'] ?? null) ? $programmeProfile['activities'] : [],
            'geography'  => is_array($programmeProfile['geography']  ?? null) ? $programmeProfile['geography']  : [],
            'audiences'  => is_array($programmeProfile['audiences']  ?? null) ? $programmeProfile['audiences']  : [],
        ];

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

        $prompt = $promptBuilder->build(
            $safeProfile,
            $overlappingEntries->toArray(),
            $analysisScope,
            $analysisScopeDetail
        );

        try {
            $groq      = App::make(GroqService::class);
            $aiResponse = $groq->generateContent($prompt);

            $submission->update(['status' => 'analysed']);

            return response()->json([
                'message' => 'Advisory note generated successfully.',
                'data'    => $aiResponse,
            ]);
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode();
            $httpStatus = in_array($statusCode, [400, 401, 403, 404, 429, 500, 502, 503]) ? $statusCode : 503;

            if ($httpStatus < 100 || $httpStatus > 599) {
                $httpStatus = 503;
            }

            Log::warning('Advisory note generation failed', [
                'submission_id' => $submission->id,
                'status_code'   => $statusCode,
                'user_id'       => $user->id,
            ]);

            return response()->json(['message' => $e->getMessage()], $httpStatus);
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

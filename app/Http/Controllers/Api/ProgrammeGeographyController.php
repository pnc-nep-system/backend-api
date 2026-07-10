<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeGeographyRequest;
use App\Http\Resources\ProgrammeGeographyResource;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeGeography;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Programme Geography",
 *     description="Section 3 — provinces, districts, and countries covered by a programme entry"
 * )
 */
class ProgrammeGeographyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/programme-entries/{programmeEntry}/geography",
     *     tags={"Programme Geography"},
     *     summary="List saved geography for a programme entry",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="programmeEntry",
     *         in="path",
     *         required=true,
     *         description="Programme entry ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Geography retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/ProgrammeGeography")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Programme entry not found")
     * )
     */
    public function index(ProgrammeEntry $programmeEntry): JsonResponse
    {
        $geography = $programmeEntry->geography()
            ->with(['province', 'district'])
            ->get();

        return response()->json([
            'data' => ProgrammeGeographyResource::collection($geography),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/programme-entries/{programmeEntry}/geography",
     *     tags={"Programme Geography"},
     *     summary="Save Section 3 geography (provinces, districts, countries)",
     *     description="Replaces all existing geography rows for this programme entry with the submitted set. Districts, if provided, must belong to their declared province.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="programmeEntry",
     *         in="path",
     *         required=true,
     *         description="Programme entry ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="provinces",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="province_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="district_ids",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={4, 7},
     *                         description="Optional — omit or send empty array for zero districts"
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="countries",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"USA", "United Kingdom"},
     *                 description="Free-text countries outside the covered provinces"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Programme geography saved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Programme geography saved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/ProgrammeGeography")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Programme entry not found"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed (e.g. district does not belong to declared province)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The provinces.0.district_ids field is invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"provinces.0.district_ids": {"One or more selected districts do not belong to the selected province."}}
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreProgrammeGeographyRequest $request, ProgrammeEntry $programmeEntry): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($programmeEntry, $validated) {
            $programmeEntry->geography()->delete();

            $rows = [];
            $now = now();

            foreach ($validated['provinces'] ?? [] as $entry) {
                $districtIds = $entry['district_ids'] ?? [];

                if (empty($districtIds)) {
                    $rows[] = [
                        'programme_entry_id' => $programmeEntry->id,
                        'province_id'        => $entry['province_id'],
                        'district_id'        => null,
                        'country'            => null,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                } else {
                    foreach ($districtIds as $districtId) {
                        $rows[] = [
                            'programme_entry_id' => $programmeEntry->id,
                            'province_id'        => $entry['province_id'],
                            'district_id'        => $districtId,
                            'country'            => null,
                            'created_at'         => $now,
                            'updated_at'         => $now,
                        ];
                    }
                }
            }

            foreach ($validated['countries'] ?? [] as $country) {
                $rows[] = [
                    'programme_entry_id' => $programmeEntry->id,
                    'province_id'        => null,
                    'district_id'        => null,
                    'country'            => $country,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            if (!empty($rows)) {
                ProgrammeGeography::insert($rows);
            }
        });

        $geography = $programmeEntry->geography()->with(['province', 'district'])->get();

        return response()->json([
            'message' => 'Programme geography saved successfully.',
            'data'    => ProgrammeGeographyResource::collection($geography),
        ], 201);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryNote;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "DashboardStats",
    type: "object",
    properties: [
        new OA\Property(property: "total_organizations", type: "integer", example: 42),
        new OA\Property(property: "total_program_entries", type: "integer", example: 156),
        new OA\Property(property: "unverified_program_entries", type: "integer", example: 12),
        new OA\Property(property: "coordinator_advisory_notes", type: "integer", nullable: true, example: 5),
        new OA\Property(property: "total_advisory_notes", type: "integer", example: 28),
    ]
)]
class DashboardController extends Controller
{
    #[OA\Get(
        path: "/dashboard/stats",
        summary: "Get dashboard statistics",
        description: "Returns aggregate counts for the dashboard. Accessible to NEP Admins and Coordinators. For admins, coordinator_advisory_notes is null.",
        security: [["bearerAuth" => []]],
        tags: ["Dashboard"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard statistics retrieved successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/DashboardStats")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin or nep_coordinator"),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalOrganizations = Organisation::count();
        $totalProgramEntries = ProgrammeEntry::count();
        $unverifiedProgramEntries = ProgrammeEntry::where('is_unverified', true)->count();
        $totalAdvisoryNotes = AdvisoryNote::count();

        $coordinatorAdvisoryNotes = $user->isNepAdmin()
            ? null
            : AdvisoryNote::where('coordinator_id', $user->id)->count();

        return response()->json([
            'total_organizations' => $totalOrganizations,
            'total_program_entries' => $totalProgramEntries,
            'unverified_program_entries' => $unverifiedProgramEntries,
            'coordinator_advisory_notes' => $coordinatorAdvisoryNotes,
            'total_advisory_notes' => $totalAdvisoryNotes,
        ]);
    }
}

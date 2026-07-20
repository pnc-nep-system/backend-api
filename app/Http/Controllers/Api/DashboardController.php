<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryNote;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $totalProgramEntries = ProgrammeEntry::where('is_submitted', true)->count();
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

    public function recentActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->isNepAdmin();

        // 1. Advisory notes delivered
        $notesQuery = AdvisoryNote::where('status', 'delivered');
        if (!$isAdmin) {
            $notesQuery->where('coordinator_id', $user->id);
        }
        $notes = $notesQuery->orderBy('delivered_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($note) => [
                'type'            => 'advisory_note',
                'id'              => $note->id,
                'label'           => $note->submitting_party,
                'occurred_at'     => $note->delivered_at,
            ]);

        // 2. New submitted entries (first-time: created_at = last_updated_at)
        //    Match to a draft advisory note by submitting_party = org name
        $draftNotes = AdvisoryNote::whereIn('status', ['pending', 'analysed'])
            ->get()
            ->keyBy('submitting_party');

        $newEntries = ProgrammeEntry::with('organisation')
            ->where('is_submitted', true)
            ->whereColumn('last_updated_at', '<=', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($entry) use ($draftNotes) {
                $orgName = $entry->organisation->name ?? 'Unknown';
                $advisoryNote = $draftNotes->get($orgName);
                return [
                    'type'            => 'programme_new',
                    'id'              => $entry->id,
                    'label'           => $orgName,
                    'programme'       => $entry->programme_name,
                    'advisory_note_id'=> $advisoryNote?->id,
                    'occurred_at'     => $entry->created_at,
                ];
            });

        // 3. Updated submitted entries (re-submitted: last_updated_at > created_at)
        $updatedEntries = ProgrammeEntry::with('organisation')
            ->where('is_submitted', true)
            ->whereColumn('last_updated_at', '>', 'created_at')
            ->orderBy('last_updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($entry) => [
                'type'        => 'programme_updated',
                'id'          => $entry->id,
                'label'       => $entry->organisation->name ?? 'Unknown',
                'programme'   => $entry->programme_name,
                'occurred_at' => $entry->last_updated_at,
            ]);

        $activity = collect()
            ->merge($notes)
            ->merge($newEntries)
            ->merge($updatedEntries)
            ->sortByDesc('occurred_at')
            ->values()
            ->take(15);

        return response()->json($activity);
    }
}

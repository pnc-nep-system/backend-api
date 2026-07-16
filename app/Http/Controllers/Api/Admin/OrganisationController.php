<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Organisation",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Green Earth Nepal"),
        new OA\Property(property: "contact_name", type: "string", example: "Ram Sharma"),
        new OA\Property(property: "email", type: "string", format: "email", example: "info@greenearth.org"),
        new OA\Property(property: "member_since", type: "integer", example: 2020),
        new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
        new OA\Property(property: "last_inactive_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "users_count", type: "integer", example: 5),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class OrganisationController extends Controller
{
    #[OA\Get(
        path: "/admin/organisations",
        tags: ["Admin - Organisations"],
        summary: "List all organisations",
        description: "Only accessible to nep_admin. Supports filtering by status and search.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false,
                schema: new OA\Schema(type: "string", enum: ["active", "inactive"])
            ),
            new OA\Parameter(name: "search", in: "query", required: false,
                description: "Search by organisation name",
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of organisations",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Organisation")),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $organisations = Organisation::query()
            ->withCount('users')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($organisations);
    }
}

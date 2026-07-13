<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Province;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Province",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "province_name", type: "string"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "District",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "province_id", type: "integer"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
    type: "object"
)]
class LocationController extends Controller
{
    #[OA\Get(
        path: "/provinces",
        summary: "Get all provinces",
        description: "Returns a list of all provinces for populating frontend dropdowns.",
        security: [["bearerAuth" => []]],
        tags: ["Locations"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of provinces",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Province")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index()
    {
        return response()->json(['data' => Province::all()]);
    }

    #[OA\Get(
        path: "/provinces/{province}/districts",
        summary: "Get districts by province",
        description: "Returns districts belonging to the specified province.",
        security: [["bearerAuth" => []]],
        tags: ["Locations"],
        parameters: [
            new OA\Parameter(
                name: "province",
                in: "path",
                required: true,
                description: "Province ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of districts for the province",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/District")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Province not found"),
        ]
    )]
    public function districts(Province $province)
    {
        return response()->json(['data' => $province->districts]);
    }
}

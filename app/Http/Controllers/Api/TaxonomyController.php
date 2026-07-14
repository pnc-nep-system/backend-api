<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class TaxonomyController extends Controller
{
    #[OA\Schema(
        schema: "TaxonomyCategory",
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer", example: 1),
            new OA\Property(property: "code", type: "string", example: "education"),
            new OA\Property(property: "label", type: "string", example: "Education"),
            new OA\Property(property: "is_active", type: "boolean", example: true),
            new OA\Property(property: "version", type: "string", example: "2026-07-14T10:00:00Z", nullable: true),
            new OA\Property(property: "created_at", type: "string", format: "date-time"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        ]
    )]

    #[OA\Schema(
        schema: "TaxonomySubcategory",
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer", example: 1),
            new OA\Property(property: "category_id", type: "integer", example: 1),
            new OA\Property(property: "code", type: "string", example: "primary_education"),
            new OA\Property(property: "label", type: "string", example: "Primary Education"),
            new OA\Property(property: "is_active", type: "boolean", example: true),
            new OA\Property(property: "version", type: "string", example: "2026-07-14T10:00:00Z", nullable: true),
            new OA\Property(property: "created_at", type: "string", format: "date-time"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        ]
    )]

    #[OA\Schema(
        schema: "TaxonomyItem",
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer", example: 1),
            new OA\Property(property: "subcategory_id", type: "integer", example: 1),
            new OA\Property(property: "code", type: "string", example: "teacher_training"),
            new OA\Property(property: "label", type: "string", example: "Teacher Training"),
            new OA\Property(property: "is_active", type: "boolean", example: true),
            new OA\Property(property: "is_other", type: "boolean", example: false),
            new OA\Property(property: "version", type: "string", example: "2026-07-14T10:00:00Z", nullable: true),
            new OA\Property(property: "created_at", type: "string", format: "date-time"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        ]
    )]

    // ==================== CATEGORIES ====================

    #[OA\Get(
        path: "/taxonomy/categories",
        summary: "List all taxonomy categories",
        description: "Returns all taxonomy categories with their subcategories and items",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of categories",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/TaxonomyCategory")
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function listCategories()
    {
        $categories = ActivityCategory::with(['subcategories.items'])->get();
        return response()->json($categories);
    }

    #[OA\Post(
        path: "/taxonomy/categories",
        summary: "Create a new taxonomy category",
        description: "Creates a new taxonomy category. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["code", "label"],
                properties: [
                    new OA\Property(property: "code", type: "string", example: "health"),
                    new OA\Property(property: "label", type: "string", example: "Health"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Category created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomyCategory")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function createCategory(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'code' => 'required|string|unique:taxonomy_categories,code|max:255',
            'label' => 'required|string|max:255',
        ]);

        $category = ActivityCategory::create($validated);

        return response()->json($category, 201);
    }

    #[OA\Put(
        path: "/taxonomy/categories/{category}",
        summary: "Rename a taxonomy category",
        description: "Updates the label of a taxonomy category and increments the version timestamp. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        parameters: [
            new OA\Parameter(
                name: "category",
                in: "path",
                required: true,
                description: "Category ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["label"],
                properties: [
                    new OA\Property(property: "label", type: "string", example: "Health and Nutrition"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Category renamed successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomyCategory")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 404, description: "Category not found"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function renameCategory(Request $request, ActivityCategory $category)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $category->update([
            'label' => $validated['label'],
            'version' => now()->toIso8601String(),
        ]);

        return response()->json($category);
    }

    #[OA\Patch(
        path: "/taxonomy/categories/{category}/deprecate",
        summary: "Deprecate a taxonomy category",
        description: "Sets is_active to false and increments the version timestamp. Does not delete the category. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        parameters: [
            new OA\Parameter(
                name: "category",
                in: "path",
                required: true,
                description: "Category ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Category deprecated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomyCategory")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 404, description: "Category not found"),
        ]
    )]
    public function deprecateCategory(Request $request, ActivityCategory $category)
    {
        $this->authorizeAdmin($request);

        $category->update([
            'is_active' => false,
            'version' => now()->toIso8601String(),
        ]);

        return response()->json($category);
    }

    // ==================== SUBCATEGORIES ====================

    #[OA\Post(
        path: "/taxonomy/subcategories",
        summary: "Create a new taxonomy subcategory",
        description: "Creates a new taxonomy subcategory under a category. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category_id", "code", "label"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "code", type: "string", example: "primary_ed"),
                    new OA\Property(property: "label", type: "string", example: "Primary Education"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Subcategory created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomySubcategory")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function createSubcategory(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'category_id' => 'required|exists:taxonomy_categories,id',
            'code' => 'required|string|unique:taxonomy_subcategories,code|max:255',
            'label' => 'required|string|max:255',
        ]);

        $subcategory = ActivitySubcategory::create($validated);

        return response()->json($subcategory, 201);
    }

    #[OA\Put(
        path: "/taxonomy/subcategories/{subcategory}",
        summary: "Rename a taxonomy subcategory",
        description: "Updates the label of a taxonomy subcategory and increments the version timestamp. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        parameters: [
            new OA\Parameter(
                name: "subcategory",
                in: "path",
                required: true,
                description: "Subcategory ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["label"],
                properties: [
                    new OA\Property(property: "label", type: "string", example: "Primary and Secondary Education"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Subcategory renamed successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomySubcategory")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 404, description: "Subcategory not found"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function renameSubcategory(Request $request, ActivitySubcategory $subcategory)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $subcategory->update([
            'label' => $validated['label'],
            'version' => now()->toIso8601String(),
        ]);

        return response()->json($subcategory);
    }

    #[OA\Patch(
        path: "/taxonomy/subcategories/{subcategory}/deprecate",
        summary: "Deprecate a taxonomy subcategory",
        description: "Sets is_active to false and increments the version timestamp. Does not delete the subcategory. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        parameters: [
            new OA\Parameter(
                name: "subcategory",
                in: "path",
                required: true,
                description: "Subcategory ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Subcategory deprecated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomySubcategory")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 404, description: "Subcategory not found"),
        ]
    )]
    public function deprecateSubcategory(Request $request, ActivitySubcategory $subcategory)
    {
        $this->authorizeAdmin($request);

        $subcategory->update([
            'is_active' => false,
            'version' => now()->toIso8601String(),
        ]);

        return response()->json($subcategory);
    }

    // ==================== ITEMS ====================

    #[OA\Post(
        path: "/taxonomy/items",
        summary: "Create a new taxonomy item",
        description: "Creates a new taxonomy item under a subcategory. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["subcategory_id", "code", "label"],
                properties: [
                    new OA\Property(property: "subcategory_id", type: "integer", example: 1),
                    new OA\Property(property: "code", type: "string", example: "teacher_training"),
                    new OA\Property(property: "label", type: "string", example: "Teacher Training"),
                    new OA\Property(property: "is_other", type: "boolean", example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Item created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomyItem")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function createItem(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'subcategory_id' => 'required|exists:taxonomy_subcategories,id',
            'code' => 'required|string|unique:taxonomy_items,code|max:255',
            'label' => 'required|string|max:255',
            'is_other' => 'sometimes|boolean',
        ]);

        $item = ActivityItem::create($validated);

        return response()->json($item, 201);
    }

    #[OA\Put(
        path: "/taxonomy/items/{item}",
        summary: "Rename a taxonomy item",
        description: "Updates the label of a taxonomy item and increments the version timestamp. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        parameters: [
            new OA\Parameter(
                name: "item",
                in: "path",
                required: true,
                description: "Item ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["label"],
                properties: [
                    new OA\Property(property: "label", type: "string", example: "Teacher Training and Development"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Item renamed successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomyItem")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 404, description: "Item not found"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function renameItem(Request $request, ActivityItem $item)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $item->update([
            'label' => $validated['label'],
            'version' => now()->toIso8601String(),
        ]);

        return response()->json($item);
    }

    #[OA\Patch(
        path: "/taxonomy/items/{item}/deprecate",
        summary: "Deprecate a taxonomy item",
        description: "Sets is_active to false and increments the version timestamp. Does not delete the item. Only accessible by NEP Admin.",
        security: [["bearerAuth" => []]],
        tags: ["Taxonomy"],
        parameters: [
            new OA\Parameter(
                name: "item",
                in: "path",
                required: true,
                description: "Item ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Item deprecated successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/TaxonomyItem")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - NEP Admin only"),
            new OA\Response(response: 404, description: "Item not found"),
        ]
    )]
    public function deprecateItem(Request $request, ActivityItem $item)
    {
        $this->authorizeAdmin($request);

        $item->update([
            'is_active' => false,
            'version' => now()->toIso8601String(),
        ]);

        return response()->json($item);
    }

    /**
     * Ensure the user has NEP Admin role.
     */
    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        if (! $user || $user->role !== 'nep_admin') {
            abort(403, 'Forbidden. You do not have the required access level.');
        }
    }
}
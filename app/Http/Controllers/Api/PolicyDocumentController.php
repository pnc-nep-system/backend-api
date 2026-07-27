<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "PolicyDocument",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "National Education Policy 2026"),
        new OA\Property(property: "authority", type: "string", example: "Ministry of Education"),
        new OA\Property(property: "version", type: "string", example: "1.0"),
        new OA\Property(property: "date", type: "string", format: "date", example: "2026-01-15"),
        new OA\Property(property: "status", type: "string", example: "active", enum: ["active", "inactive", "superseded"]),
        new OA\Property(property: "file_url", type: "string", nullable: true, example: "https://example.com/policy.pdf"),
        new OA\Property(property: "created_by", type: "integer", example: 1),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class PolicyDocumentController extends Controller
{
    #[OA\Get(
        path: "/policy-documents",
        summary: "List all policy documents",
        description: "Returns all policy documents. Accessible by all authenticated users.",
        security: [["bearerAuth" => []]],
        tags: ["Policy Documents"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of policy documents",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/PolicyDocument")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index(): JsonResponse
    {
        $documents = PolicyDocument::with('creator')
            ->latest()
            ->get();

        return response()->json([
            'data' => $documents,
        ]);
    }

    #[OA\Post(
        path: "/policy-documents",
        summary: "Create a new policy document",
        description: "Creates a new policy document. Only NEP Admins and Coordinators can create documents.",
        security: [["bearerAuth" => []]],
        tags: ["Policy Documents"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["title", "authority", "version", "date"],
                properties: [
                    new OA\Property(property: "title", type: "string", example: "National Education Policy 2026"),
                    new OA\Property(property: "authority", type: "string", example: "Ministry of Education"),
                    new OA\Property(property: "version", type: "string", example: "1.0"),
                    new OA\Property(property: "date", type: "string", format: "date", example: "2026-01-15"),
                    new OA\Property(property: "status", type: "string", example: "active", enum: ["active", "inactive", "superseded"]),
                    new OA\Property(property: "file_url", type: "string", nullable: true, example: "https://example.com/policy.pdf"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Policy document created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Policy document created successfully."),
                        new OA\Property(property: "data", ref: "#/components/schemas/PolicyDocument"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'authority' => ['required', 'string', 'max:255'],
            'version'   => ['required', 'string', 'max:20'],
            'date'      => ['required', 'date'],
            'status'    => ['sometimes', 'in:active,inactive,superseded'],
            'file_url'  => ['nullable', 'string', 'max:2048'],
            'file'      => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:51200'],
        ]);

        try {
            $validated['created_by'] = $request->user()->id;

            // Handle file upload
            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('policy-documents', 'public');
                $validated['file_url'] = $path;
            }

            $document = PolicyDocument::create($validated);

            return response()->json([
                'message' => 'Policy document created successfully.',
                'data'    => $document,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create policy document', [
                'error'   => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'An error occurred while creating the policy document.',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/policy-documents/{id}",
        summary: "Get a single policy document",
        description: "Returns the details of a specific policy document.",
        security: [["bearerAuth" => []]],
        tags: ["Policy Documents"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Policy document details",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/PolicyDocument"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Not Found"),
        ]
    )]
    public function show(PolicyDocument $policyDocument): JsonResponse
    {
        $policyDocument->load('creator');

        return response()->json([
            'data' => $policyDocument,
        ]);
    }

    #[OA\Patch(
        path: "/policy-documents/{id}",
        summary: "Update a policy document",
        description: "Updates an existing policy document. Only NEP Admins and Coordinators can update documents.",
        security: [["bearerAuth" => []]],
        tags: ["Policy Documents"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", example: "National Education Policy 2026"),
                    new OA\Property(property: "authority", type: "string", example: "Ministry of Education"),
                    new OA\Property(property: "version", type: "string", example: "2.0"),
                    new OA\Property(property: "date", type: "string", format: "date", example: "2026-06-01"),
                    new OA\Property(property: "status", type: "string", example: "active", enum: ["active", "inactive", "superseded"]),
                    new OA\Property(property: "file_url", type: "string", nullable: true, example: "https://example.com/policy-v2.pdf"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Policy document updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Policy document updated successfully."),
                        new OA\Property(property: "data", ref: "#/components/schemas/PolicyDocument"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not Found"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function update(Request $request, PolicyDocument $policyDocument): JsonResponse
    {
        $validated = $request->validate([
            'title'     => ['sometimes', 'string', 'max:255'],
            'authority' => ['sometimes', 'string', 'max:255'],
            'version'   => ['sometimes', 'string', 'max:20'],
            'date'      => ['sometimes', 'date'],
            'status'    => ['sometimes', 'in:active,inactive,superseded'],
            'file_url'  => ['nullable', 'string', 'max:2048'],
            'file'      => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:51200'],
        ]);

        try {
            // Handle file upload
            if ($request->hasFile('file')) {
                // Delete old file if it exists and stored locally
                if ($policyDocument->file_url && !str_starts_with($policyDocument->file_url, 'http')) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($policyDocument->file_url);
                }
                $path = $request->file('file')->store('policy-documents', 'public');
                $validated['file_url'] = $path;
            }

            $policyDocument->update($validated);

            return response()->json([
                'message' => 'Policy document updated successfully.',
                'data'    => $policyDocument->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update policy document', [
                'error'       => $e->getMessage(),
                'user_id'     => $request->user()->id,
                'document_id' => $policyDocument->id,
            ]);

            return response()->json([
                'message' => 'An error occurred while updating the policy document.',
            ], 500);
        }
    }

    #[OA\Delete(
        path: "/policy-documents/{id}",
        summary: "Delete a policy document",
        description: "Deletes a policy document. Only NEP Admins and Coordinators can delete documents.",
        security: [["bearerAuth" => []]],
        tags: ["Policy Documents"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Policy document deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Policy document deleted successfully."),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not Found"),
        ]
    )]
    public function destroy(Request $request, PolicyDocument $policyDocument): JsonResponse
    {
        try {
            $policyDocument->delete();

            return response()->json([
                'message' => 'Policy document deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete policy document', [
                'error'       => $e->getMessage(),
                'user_id'     => $request->user()->id,
                'document_id' => $policyDocument->id,
            ]);

            return response()->json([
                'message' => 'An error occurred while deleting the policy document.',
            ], 500);
        }
    }

    public function getFile(PolicyDocument $policyDocument)
    {
        if (! $policyDocument->file_url) {
            return response()->json(['message' => 'No file associated with this document.'], 404);
        }

        if (Storage::disk('public')->exists($policyDocument->file_url)) {
            return Storage::disk('public')->response($policyDocument->file_url);
        }

        $fullPath = storage_path('app/public/' . $policyDocument->file_url);
        if (file_exists($fullPath)) {
            return response()->file($fullPath);
        }

        return response()->json(['message' => 'File not found on server.'], 404);
    }
}
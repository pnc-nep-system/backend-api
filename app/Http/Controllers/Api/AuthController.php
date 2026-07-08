<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/login",
        summary: "User Login",
        description: "Authenticate a user with email and password and return a Sanctum access token.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Login successful."),
                        new OA\Property(property: "user", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "name", type: "string", example: "John Doe"),
                            new OA\Property(property: "email", type: "string", example: "user@example.com"),
                            new OA\Property(property: "role", type: "string", example: "member_org"),
                            new OA\Property(property: "status", type: "string", example: "active"),
                            new OA\Property(property: "organisation_id", type: "integer", example: 12, nullable: true),
                            new OA\Property(property: "organisation", type: "object", nullable: true, properties: [
                                new OA\Property(property: "id", type: "integer", example: 12),
                                new OA\Property(property: "name", type: "string", example: "Example NGO"),
                            ]),
                        ]),
                        new OA\Property(property: "token", type: "string", example: "1|zR8hF2a..."),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Invalid credentials."),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Validation failed."),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $validator->validated();

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->load('organisation');

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'organisation_id' => $user->organisation_id,
                'organisation' => $user->organisation ? [
                    'id' => $user->organisation->id,
                    'name' => $user->organisation->name,
                ] : null,
            ],
            'token' => $token,
        ]);
    }

    #[OA\Get(
        path: "/session",
        summary: "Get Current Session",
        description: "Retrieve the role and organisation context of the currently authenticated user.",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Session details retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "name", type: "string", example: "John Doe"),
                            new OA\Property(property: "email", type: "string", example: "user@example.com"),
                            new OA\Property(property: "role", type: "string", example: "member_org"),
                            new OA\Property(property: "status", type: "string", example: "active"),
                            new OA\Property(property: "organisation_id", type: "integer", example: 12, nullable: true),
                            new OA\Property(property: "organisation", type: "object", nullable: true, properties: [
                                new OA\Property(property: "id", type: "integer", example: 12),
                                new OA\Property(property: "name", type: "string", example: "Example NGO"),
                            ]),
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Unauthenticated."),
                    ]
                )
            ),
        ]
    )]
    public function session(Request $request)
    {
        $user = $request->user()->load('organisation');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'organisation_id' => $user->organisation_id,
                'organisation' => $user->organisation ? [
                    'id' => $user->organisation->id,
                    'name' => $user->organisation->name,
                ] : null,
            ],
        ]);
    }

    #[OA\Post(
        path: "/logout",
        summary: "User Logout",
        description: "Invalidate the current Sanctum token session.",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logged out successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Logged out successfully."),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Unauthenticated."),
                    ]
                )
            ),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
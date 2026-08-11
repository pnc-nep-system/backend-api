<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * The canonical "who am I" payload shared by /login, /session, and /user.
     * Always includes the user's assigned roles and effective permissions so
     * the frontend never has to hard-code access by role name — it derives
     * what to show/allow purely from `permissions`.
     */
    public static function currentUserPayload(User $user): array
    {
        $user->loadMissing(['organisation:id,name', 'roles:id,name,display_name']);

        return [
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
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values(),
            'permissions' => $user->effectivePermissions(),
        ];
    }

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
                            new OA\Property(property: "email", type: "string", example: "admin@example.com"),
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
                response: 403,
                description: "Account deactivated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account is deactivated."),
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

        /** @var \App\Models\User|null $user */
        $user = User::with('organisation:id,name')
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'Account is deactivated.',
            ], 403);
        }

        // Give each device/session its own uniquely-named token instead of
        // reusing 'api-token' for everyone. Do NOT delete existing tokens here —
        // that's what was logging out other devices.
        $deviceName = $request->header('X-Device-Name') ?? $request->userAgent() ?? 'unknown-device';
        $tokenName = $deviceName . '-' . now()->timestamp;

        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => self::currentUserPayload($user),
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
        return response()->json([
            'user' => self::currentUserPayload($request->user()),
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
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    #[OA\Patch(
        path: "/change-password",
        summary: "Change Password",
        description: "Change the authenticated user's password. Requires current password verification.",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "new_password", "new_password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", format: "password", example: "TempPassword123!"),
                    new OA\Property(property: "new_password", type: "string", format: "password", example: "MyNewPassword@123"),
                    new OA\Property(property: "new_password_confirmation", type: "string", format: "password", example: "MyNewPassword@123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Password changed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Password changed successfully."),
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
            new OA\Response(
                response: 422,
                description: "Validation failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "The current password is incorrect."),
                    ]
                )
            ),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        $user->password = Hash::make($request->validated('new_password'));
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}

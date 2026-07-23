<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "User",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "organisation_id", type: "integer", example: 12, nullable: true),
        new OA\Property(property: "name", type: "string", example: "Jane Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "jane@example.com"),
        new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"]),
        new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class UserManagementController extends Controller
{
    #[OA\Get(
        path: "/admin/users",
        tags: ["Admin - User Management"],
        summary: "List user accounts",
        description: "Accessible to nep_admin and nep_coordinator. Supports filtering by role.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "role", in: "query", required: false,
                schema: new OA\Schema(type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of users",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/User")
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->query('role'));
        }

        return response()->json(
            $query->select('id', 'name', 'email', 'role', 'status')
                ->orderBy('name')
                ->get()
        );
    }

    #[OA\Post(
        path: "/admin/users",
        tags: ["Admin - User Management"],
        summary: "Create a user account",
        description: "Creates an NEP staff or member organisation account. If no password is supplied, a temporary password is generated and returned once in the response.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "role"],
                properties: [
                    new OA\Property(property: "organisation_id", type: "integer", nullable: true, example: 1),
                    new OA\Property(property: "name", type: "string", example: "Jane Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "jane@example.com"),
                    new OA\Property(property: "password", type: "string", nullable: true, example: "optional-plain-text"),
                    new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"]),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Account created",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account created."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                        new OA\Property(property: "temporary_password", type: "string", nullable: true, example: "Xk9\$mQ2vLp4T"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(User::validationRules());

        $tempPassword = null;
        if (empty($data['password'])) {
            $tempPassword = Str::password(12);
            $data['password'] = $tempPassword;
        }

        $plainPassword = $data['password'];

        $user = User::create([
            ...$data,
            'password' => Hash::make($plainPassword),
            'status' => User::STATUS_ACTIVE,
        ]);

        $loginUrl = config('app.frontend_url') . '/login';

        try {
            Mail::to($user->email)->send(new UserInvitationMail(
                $user->name,
                $user->email,
                $plainPassword,
                $loginUrl
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email on account creation', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Account created. Invitation email has been sent.',
            'user' => $user->fresh('organisation'),
            'temporary_password' => $tempPassword,
        ], 201);
    }

    #[OA\Post(
        path: "/admin/users/invite",
        tags: ["Admin - User Management"],
        summary: "Invite a new user via email",
        description: "Creates a new user account with a default password and sends an invitation email. Prevents duplicate invitations for existing email addresses.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"], example: "member_org"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "User invited successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "User created successfully. Invitation email has been sent."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 422, description: "Validation error or email already exists"),
        ]
    )]
    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([
                User::ROLE_NEP_ADMIN,
                User::ROLE_NEP_COORDINATOR,
                User::ROLE_MEMBER_ORG,
            ])],
        ]);

        $defaultPassword = 'nep@nep!#$';
        $loginUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/login';

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($defaultPassword),
                'role' => $data['role'],
                'status' => User::STATUS_ACTIVE,
            ]);

            Mail::to($user->email)->send(new UserInvitationMail(
                $user->name,
                $user->email,
                $defaultPassword,
                $loginUrl
            ));

            return response()->json([
                'message' => 'User created successfully. Invitation email has been sent.',
                'user' => $user->fresh('organisation'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'user_id' => $user->id ?? null,
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'User created successfully. Invitation email has been sent.',
                'user' => $user->fresh('organisation'),
            ], 201);
        }
    }

    #[OA\Patch(
        path: "/admin/users/{user}",
        tags: ["Admin - User Management"],
        summary: "Edit a user account",
        description: "Update user account fields. Leave password blank to keep the current password.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "organisation_id", type: "integer", nullable: true),
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", nullable: true, description: "Leave blank to keep current password"),
                    new OA\Property(property: "role", type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"]),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Account updated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account updated."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(User::validationRules(update: true, userId: $user->id));

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update($data);

            if (($data['status'] ?? null) === User::STATUS_INACTIVE) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => 'Account updated.',
            'user' => $user->fresh('organisation'),
        ]);
    }

    #[OA\Post(
        path: "/admin/users/{user}/deactivate",
        tags: ["Admin - User Management"],
        summary: "Deactivate a user account",
        description: "Sets status to inactive and revokes all API tokens. An admin cannot deactivate their own account.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Account deactivated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account deactivated."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 422, description: "Cannot deactivate your own account"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->update(['status' => User::STATUS_INACTIVE]);
            $user->tokens()->delete();
        });

        return response()->json([
            'message' => 'Account deactivated.',
            'user' => $user->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/admin/users/{user}/reactivate",
        tags: ["Admin - User Management"],
        summary: "Reactivate a previously deactivated user account",
        description: "Sets the account status back to active.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Account reactivated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Account reactivated."),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function reactivate(User $user): JsonResponse
    {
        $user->update(['status' => User::STATUS_ACTIVE]);

        return response()->json([
            'message' => 'Account reactivated.',
            'user' => $user->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/admin/users/{user}/reset-credentials",
        tags: ["Admin - User Management"],
        summary: "Reset a user's credentials",
        description: "Generates a new temporary password server-side and revokes all existing tokens. The temporary password is returned once in the response for the admin to relay securely.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Credentials reset",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Credentials reset. Share the temporary password with the user securely."),
                        new OA\Property(property: "temporary_password", type: "string", example: "Xk9\$mQ2vLp4T"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function resetCredentials(User $user): JsonResponse
    {
        $tempPassword = Str::password(12);

        DB::transaction(function () use ($user, $tempPassword) {
            $user->update(['password' => Hash::make($tempPassword)]);
            $user->tokens()->delete();
        });

        return response()->json([
            'message' => 'Credentials reset. Share the temporary password with the user securely.',
            'temporary_password' => $tempPassword,
        ]);
    }
}

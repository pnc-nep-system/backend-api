<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Role;
use App\Models\User;
use App\Services\Mail\SmtpDiagnostics;
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
        description: "Only accessible to nep_admin. Supports filtering by role and status.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "role", in: "query", required: false,
                schema: new OA\Schema(type: "string", enum: ["nep_admin", "nep_coordinator", "member_org"])
            ),
            new OA\Parameter(name: "status", in: "query", required: false,
                schema: new OA\Schema(type: "string", enum: ["active", "inactive"])
            ),
            // new OA\Parameter(name: "per_page", in: "query", required: false,
            //     schema: new OA\Schema(type: "integer", default: 25)
            // ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of users",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/User")),
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
        $users = User::query()
            ->with(['organisation:id,name', 'roles'])
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($users);
    }

    #[OA\Get(
        path: "/admin/users/{user}",
        tags: ["Admin - User Management"],
        summary: "Show a single user account",
        description: "Returns the user with their organisation and assigned roles (with permissions) loaded.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "User details", content: new OA\JsonContent(ref: "#/components/schemas/User")),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        $user->load(['organisation:id,name', 'roles.permissions']);

        return response()->json([
            ...$user->toArray(),
            'effective_permissions' => $user->effectivePermissions(),
        ]);
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

        if ($error = $this->rejectRoleEscalation($request, $data['role'])) {
            return $error;
        }

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

        $emailSent = true;
        try {
            Mail::to($user->email)->send(new UserInvitationMail(
                $user->name,
                $user->email,
                $plainPassword,
                $loginUrl
            ));
        } catch (\Throwable $e) {
            $emailSent = false;
            $diagnosis = SmtpDiagnostics::classify($e);
            Log::error('Failed to send invitation email on account creation', [
                'user_id' => $user->id,
                'email' => $user->email,
                'category' => $diagnosis['category'],
                'error' => $diagnosis['raw'],
            ]);
        }

        return response()->json([
            'message' => $emailSent
                ? 'Account created. Invitation email has been sent.'
                : 'Account created, but the invitation email could not be sent — check the mail server configuration ('
                    . 'admin/mail/test can help diagnose this) and use "Reset Credentials" to resend once fixed.',
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
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        if ($error = $this->rejectRoleEscalation($request, $data['role'])) {
            return $error;
        }

        $defaultPassword = Str::password(12);
        $loginUrl = config('app.frontend_url', rtrim($request->getSchemeAndHttpHost(), '/')) . '/login';

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($defaultPassword),
                'role' => $data['role'],
                'status' => User::STATUS_ACTIVE,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create invited user account', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create user.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $emailSent = true;
        try {
            Mail::to($user->email)->send(new UserInvitationMail(
                $user->name,
                $user->email,
                $defaultPassword,
                $loginUrl
            ));
        } catch (\Throwable $e) {
            $emailSent = false;
            $diagnosis = SmtpDiagnostics::classify($e);
            Log::error('Failed to send invitation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'category' => $diagnosis['category'],
                'error' => $diagnosis['raw'],
            ]);
        }

        return response()->json([
            'message' => $emailSent
                ? 'User created successfully. Invitation email has been sent.'
                : 'User created, but the invitation email could not be sent — check the mail server configuration '
                    . 'and use "Reset Credentials" to resend once fixed.',
            'user' => $user->fresh('organisation'),
        ], 201);
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

        if (array_key_exists('role', $data)) {
            // Self-service role changes are never allowed here, even for
            // nep_admin — prevents accidental self-lockout and closes off a
            // whole class of self-escalation bugs at the door. Checked before
            // the last-admin guard below so the actor gets the precise reason.
            if ($request->user()->id === $user->id) {
                return response()->json([
                    'message' => 'You cannot change your own role.',
                ], 422);
            }

            if ($error = $this->rejectRoleEscalation($request, $data['role'])) {
                return $error;
            }
        }

        // Guard the last active nep_admin from being demoted or deactivated via
        // this endpoint — doing so would lock every admin-only screen (including
        // this one) with nobody able to reverse it.
        $isLastAdminLosingAccess = $user->isLastActiveAdmin()
            && (
                (array_key_exists('role', $data) && $data['role'] !== User::ROLE_NEP_ADMIN)
                || (array_key_exists('status', $data) && $data['status'] !== User::STATUS_ACTIVE)
            );

        if ($isLastAdminLosingAccess) {
            return response()->json([
                'message' => 'Cannot change the role or status of the last active NEP Administrator.',
            ], 422);
        }

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

        if ($user->isLastActiveAdmin()) {
            return response()->json([
                'message' => 'Cannot deactivate the last active NEP Administrator.',
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

    /**
     * Privilege-escalation guard shared by store()/invite()/update(): an actor
     * may never assign a role that grants permissions they don't themselves
     * hold. Returns a 403 JsonResponse to short-circuit the caller, or null to
     * proceed. Fully dynamic — never compares role names.
     */
    private function rejectRoleEscalation(Request $request, string $roleName): ?JsonResponse
    {
        $role = Role::where('name', $roleName)->first();

        if ($role && ! $request->user()->canGrantRole($role)) {
            return response()->json([
                'message' => 'You cannot assign a role that grants permissions you do not have yourself.',
            ], 403);
        }

        return null;
    }
}

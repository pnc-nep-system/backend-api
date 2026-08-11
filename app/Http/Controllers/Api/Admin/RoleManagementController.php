<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions', 'users');

        return response()->json($role);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_system' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        // Escalation guard: you cannot mint a role carrying permissions you
        // don't hold yourself (e.g. a users.create-only actor granting
        // themselves roles.delete via a brand-new custom role).
        if (! empty($data['permissions']) && ! $request->user()->canGrantPermissionIds($data['permissions'])) {
            return response()->json([
                'message' => 'You cannot grant permissions you do not have yourself.',
            ], 403);
        }

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_system' => $data['is_system'] ?? false,
            ]);

            if (isset($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            return $role->load('permissions');
        });

        return response()->json($role, 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be modified.',
            ], 422);
        }

        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_system' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        if (isset($data['permissions']) && ! $request->user()->canGrantPermissionIds($data['permissions'])) {
            return response()->json([
                'message' => 'You cannot grant permissions you do not have yourself.',
            ], 403);
        }

        $role = DB::transaction(function () use ($role, $data) {
            $role->update([
                'display_name' => $data['display_name'] ?? $role->display_name,
                'description' => $data['description'] ?? $role->description,
                'is_system' => $data['is_system'] ?? $role->is_system,
            ]);

            if (isset($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            return $role->load('permissions');
        });

        return response()->json($role);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 422);
        }

        // Now that any role — not just the three system ones — can be a
        // user's primary `role` value, deleting one still in use would either
        // orphan that string (the user silently loses every permission) or,
        // for a user who only holds it as an additional role, quietly drop
        // their access with no way to trace why. Require reassigning users
        // first.
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return response()->json([
                'message' => "Cannot delete this role — it is still assigned to {$usersCount} user(s). Reassign them first.",
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function assignToUser(Request $request, Role $role, User $user): JsonResponse
    {
        if (! $request->user()->canGrantRole($role)) {
            return response()->json([
                'message' => 'You cannot assign a role that grants permissions you do not have yourself.',
            ], 403);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        return response()->json([
            'message' => 'Role assigned to user successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    public function removeFromUser(Request $request, Role $role, User $user): JsonResponse
    {
        // Removing the nep_admin role from the last active admin would lock
        // every admin-only screen (including this one) with nobody able to
        // reverse it — same protection as UserManagementController::update().
        if ($role->name === User::ROLE_NEP_ADMIN && $user->isLastActiveAdmin()) {
            return response()->json([
                'message' => 'Cannot remove the nep_admin role from the last active NEP Administrator.',
            ], 422);
        }

        // This role is the user's primary `role` value — detaching it here
        // (but leaving the `role` column untouched) would desync the two:
        // the user's role column would still name a role they no longer hold
        // via role_user, silently dropping every permission it granted. Send
        // the admin to change the primary role properly instead.
        if ($role->name === $user->role) {
            return response()->json([
                'message' => 'This is the user\'s primary role — change it from the Edit User form instead of removing it here.',
            ], 422);
        }

        $user->roles()->detach($role->id);

        return response()->json([
            'message' => 'Role removed from user successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('group', 'like', "%{$search}%");
                });
            })
            ->orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'group', 'description']);

        $grouped = $permissions->groupBy('group');

        return response()->json($grouped);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'group' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $permission = Permission::create($data);

        return response()->json($permission, 201);
    }

    public function updatePermission(Request $request, Permission $permission): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'group' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $permission->update($data);

        return response()->json($permission);
    }

    public function destroyPermission(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }
}
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

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function assignToUser(Request $request, Role $role, User $user): JsonResponse
    {
        $user->roles()->syncWithoutDetaching([$role->id]);

        return response()->json([
            'message' => 'Role assigned to user successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    public function removeFromUser(Request $request, Role $role, User $user): JsonResponse
    {
        $user->roles()->detach($role->id);

        return response()->json([
            'message' => 'Role removed from user successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')
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
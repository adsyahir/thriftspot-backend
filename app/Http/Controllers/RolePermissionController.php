<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        return response()->json(['data' => $roles]);
    }

    /**
     * Get all permissions
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = Permission::all()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ];
        });

        return response()->json(['data' => $permissions]);
    }

    /**
     * Create a new role
     */
    public function createRole(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        $role->load('permissions');

        return response()->json([
            'message' => 'Role created successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ], 201);
    }

    /**
     * Update a role
     */
    public function updateRole(Request $request, $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        $role->load('permissions');

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ]);
    }

    /**
     * Delete a role
     */
    public function deleteRole($id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Create a new permission
     */
    public function createPermission(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name|max:255',
        ]);

        $permission = Permission::create(['name' => $request->name]);

        return response()->json([
            'message' => 'Permission created successfully',
            'data' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ],
        ], 201);
    }

    /**
     * Update a permission
     */
    public function updatePermission(Request $request, $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,'.$id,
        ]);

        $permission->update(['name' => $request->name]);

        return response()->json([
            'message' => 'Permission updated successfully',
            'data' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ],
        ]);
    }

    /**
     * Delete a permission
     */
    public function deletePermission($id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = \App\Models\User::findOrFail($userId);
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Role assigned successfully',
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUser(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = \App\Models\User::findOrFail($userId);
        $user->removeRole($request->role);

        return response()->json([
            'message' => 'Role removed successfully',
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * Get all users with their roles
     * Supports search and pagination
     *
     * Query params:
     * - per_page: number of items per page (default: 10)
     * - search: global search across name, email, username
     * - name: filter by name
     * - email: filter by email
     * - role: filter by role name
     */
    public function getUsersWithRoles(Request $request): JsonResponse
    {
        // Get pagination and search parameters
        $perPage = $request->get('per_page', 10); // Default 10 items per page
        $search = $request->get('search'); // Global search query
        $name = $request->get('name'); // Search by name
        $email = $request->get('email'); // Search by email
        $role = $request->get('role'); // Filter by role

        // Build query
        $query = User::with('roles', 'permissions');

        // Global search across name, email, and username
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Specific field searches
        if ($name) {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($email) {
            $query->where('email', 'like', "%{$email}%");
        }

        // Filter by role name
        if ($role) {
            $query->whereHas('roles', function($q) use ($role) {
                $q->where('name', 'like', "%{$role}%");
            });
        }

        // Paginate and transform results
        $users = $query->paginate($perPage)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });

        return response()->json(['data' => $users]);
    }
}

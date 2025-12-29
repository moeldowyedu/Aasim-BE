<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantRoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tenant/roles",
     *     tags={"Tenant - Roles"},
     *     summary="List all roles for current tenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of tenant roles"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $tenant = $request->tenant;

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context not found',
                ], 400);
            }

            $roles = Role::where('tenant_id', $tenant->id)
                ->where('guard_name', 'tenant')
                ->with(['permissions:id,name'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'guard_name' => $role->guard_name,
                        'permissions' => $role->permissions->pluck('name'),
                        'permissions_count' => $role->permissions->count(),
                        'created_at' => $role->created_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/roles",
     *     tags={"Tenant - Roles"},
     *     summary="Create a new role for current tenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "permissions"},
     *             @OA\Property(property="name", type="string", example="Project Manager"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"tenant.projects.view", "tenant.projects.create"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully"
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $tenant = $request->tenant;

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context not found',
                ], 400);
            }

            // Validate request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'permissions' => 'required|array|min:1',
                'permissions.*' => 'required|string|exists:permissions,name',
            ]);

            // Check if role name already exists for this tenant
            $existingRole = Role::where('name', $validated['name'])
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'tenant')
                ->first();

            if ($existingRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role with this name already exists for this tenant',
                ], 400);
            }

            // Verify all permissions are tenant-scoped
            $permissions = Permission::whereIn('name', $validated['permissions'])
                ->where('guard_name', 'tenant')
                ->get();

            if ($permissions->count() !== count($validated['permissions'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some permissions are invalid or not tenant-scoped',
                ], 400);
            }

            // Create role
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'tenant',
                'tenant_id' => $tenant->id,
            ]);

            // Assign permissions
            $role->syncPermissions($permissions);

            // Log activity
            activity()
                ->causedBy($request->user())
                ->performedOn($role)
                ->withProperties([
                    'tenant_id' => $tenant->id,
                    'role_name' => $role->name,
                    'permissions' => $validated['permissions'],
                ])
                ->log('created_tenant_role');

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/roles/{id}",
     *     tags={"Tenant - Roles"},
     *     summary="Get role details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role details"
     *     )
     * )
     */
    public function show(Request $request, int $id)
    {
        try {
            $tenant = $request->tenant;

            $role = Role::where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'tenant')
                ->with(['permissions:id,name'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'tenant_id' => $role->tenant_id,
                    'permissions' => $role->permissions->map(function ($perm) {
                        return [
                            'id' => $perm->id,
                            'name' => $perm->name,
                        ];
                    }),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tenant/roles/{id}",
     *     tags={"Tenant - Roles"},
     *     summary="Update role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Senior Project Manager"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully"
     *     )
     * )
     */
    public function update(Request $request, int $id)
    {
        try {
            $tenant = $request->tenant;

            $role = Role::where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'tenant')
                ->firstOrFail();

            // Validate request
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'permissions' => 'sometimes|required|array|min:1',
                'permissions.*' => 'required|string|exists:permissions,name',
            ]);

            // Check if new name conflicts with existing role
            if (isset($validated['name']) && $validated['name'] !== $role->name) {
                $existingRole = Role::where('name', $validated['name'])
                    ->where('tenant_id', $tenant->id)
                    ->where('guard_name', 'tenant')
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingRole) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Role with this name already exists for this tenant',
                    ], 400);
                }

                $role->name = $validated['name'];
            }

            // Update permissions if provided
            if (isset($validated['permissions'])) {
                // Verify all permissions are tenant-scoped
                $permissions = Permission::whereIn('name', $validated['permissions'])
                    ->where('guard_name', 'tenant')
                    ->get();

                if ($permissions->count() !== count($validated['permissions'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some permissions are invalid or not tenant-scoped',
                    ], 400);
                }

                $role->syncPermissions($permissions);
            }

            $role->save();

            // Log activity
            activity()
                ->causedBy($request->user())
                ->performedOn($role)
                ->withProperties([
                    'tenant_id' => $tenant->id,
                    'role_name' => $role->name,
                    'updated_fields' => array_keys($validated),
                ])
                ->log('updated_tenant_role');

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->fresh()->permissions->pluck('name'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/roles/{id}",
     *     tags={"Tenant - Roles"},
     *     summary="Delete role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role deleted successfully"
     *     )
     * )
     */
    public function destroy(Request $request, int $id)
    {
        try {
            $tenant = $request->tenant;

            $role = Role::where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'tenant')
                ->firstOrFail();

            // Check if role is assigned to any users
            $usersCount = $role->users()->count();

            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete role. It is assigned to {$usersCount} user(s)",
                ], 400);
            }

            $roleName = $role->name;
            $role->delete();

            // Log activity
            activity()
                ->causedBy($request->user())
                ->withProperties([
                    'tenant_id' => $tenant->id,
                    'role_name' => $roleName,
                    'role_id' => $id,
                ])
                ->log('deleted_tenant_role');

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/permissions",
     *     tags={"Tenant - Roles"},
     *     summary="List available permissions for tenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of available permissions"
     *     )
     * )
     */
    public function listPermissions(Request $request)
    {
        try {
            // Get all tenant-scoped permissions from catalog
            $permissions = Permission::where('guard_name', 'tenant')
                ->orderBy('name')
                ->get(['id', 'name']);

            // Group permissions by prefix
            $grouped = $permissions->groupBy(function ($permission) {
                return explode('.', $permission->name)[1] ?? 'other';
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'all' => $permissions,
                    'grouped' => $grouped,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

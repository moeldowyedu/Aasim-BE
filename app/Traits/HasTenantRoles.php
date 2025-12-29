<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait HasTenantRoles
{
    /**
     * Get effective roles for a specific tenant.
     */
    public function getEffectiveRolesForTenant(string $tenantId, string $guardName = 'tenant')
    {
        return $this->roles()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', $guardName)
            ->get();
    }

    /**
     * Get effective permissions for a specific tenant.
     */
    public function getEffectivePermissionsForTenant(string $tenantId, string $guardName = 'tenant')
    {
        $roles = $this->getEffectiveRolesForTenant($tenantId, $guardName);

        return $roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');
    }

    /**
     * Check if user has permission in tenant context.
     */
    public function hasTenantPermission(string $permission, string $tenantId, string $guardName = 'tenant'): bool
    {
        // Get tenant-scoped permissions from roles
        $permissions = $this->getEffectivePermissionsForTenant($tenantId, $guardName);

        return $permissions->contains('name', $permission);
    }

    /**
     * Check if user has any of the permissions in tenant context.
     */
    public function hasAnyTenantPermission(array $permissions, string $tenantId, string $guardName = 'tenant'): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasTenantPermission($permission, $tenantId, $guardName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all permissions in tenant context.
     */
    public function hasAllTenantPermissions(array $permissions, string $tenantId, string $guardName = 'tenant'): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasTenantPermission($permission, $tenantId, $guardName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign role to user in tenant context.
     */
    public function assignTenantRole(string|Role $role, string $tenantId, string $guardName = 'tenant'): self
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)
                ->where('tenant_id', $tenantId)
                ->where('guard_name', $guardName)
                ->firstOrFail();
        }

        // Verify role belongs to the tenant
        if ($role->tenant_id !== $tenantId || $role->guard_name !== $guardName) {
            throw new \Exception('Role does not belong to this tenant or guard');
        }

        $this->assignRole($role);

        return $this;
    }

    /**
     * Remove role from user in tenant context.
     */
    public function removeTenantRole(string|Role $role, string $tenantId, string $guardName = 'tenant'): self
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)
                ->where('tenant_id', $tenantId)
                ->where('guard_name', $guardName)
                ->firstOrFail();
        }

        $this->removeRole($role);

        return $this;
    }

    /**
     * Sync roles for user in tenant context.
     */
    public function syncTenantRoles(array $roles, string $tenantId, string $guardName = 'tenant'): self
    {
        // Remove all existing roles for this tenant
        $existingRoles = $this->getEffectiveRolesForTenant($tenantId, $guardName);
        foreach ($existingRoles as $role) {
            $this->removeRole($role);
        }

        // Assign new roles
        foreach ($roles as $role) {
            $this->assignTenantRole($role, $tenantId, $guardName);
        }

        return $this;
    }

    /**
     * Check if user has console permission (for support/impersonation mode).
     */
    public function hasConsolePermission(string $permission): bool
    {
        $roles = $this->roles()
            ->where('guard_name', 'console')
            ->whereNull('tenant_id')
            ->get();

        $permissions = $roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');

        return $permissions->contains('name', $permission);
    }

    /**
     * Get all tenants the user belongs to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships', 'user_id', 'tenant_id')
            ->withPivot('status', 'role', 'invited_at', 'joined_at', 'left_at', 'metadata')
            ->withTimestamps();
    }

    /**
     * Get active tenant memberships only.
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('status', 'active');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // =====================================================================
        // CONSOLE PERMISSIONS (guard_name = 'console')
        // For Obsolio Team (Admin/Support Dashboard)
        // =====================================================================
        $consolePermissions = [
            // Tenant Management
            'console.tenants.view',
            'console.tenants.create',
            'console.tenants.edit',
            'console.tenants.delete',
            'console.tenants.activate',
            'console.tenants.deactivate',

            // User Management (Global)
            'console.users.view',
            'console.users.create',
            'console.users.edit',
            'console.users.delete',

            // Organization Management
            'console.organizations.view',
            'console.organizations.create',
            'console.organizations.edit',
            'console.organizations.deactivate',

            // Subscription Management
            'console.subscriptions.view',
            'console.subscriptions.create',
            'console.subscriptions.edit',
            'console.subscriptions.cancel',
            'console.subscriptions.delete',

            // Agent Management (Global Catalog)
            'console.agents.view',
            'console.agents.create',
            'console.agents.edit',
            'console.agents.delete',
            'console.agents.publish',

            // Permission Catalog Management
            'console.permissions.view',
            'console.permissions.create',
            'console.permissions.edit',
            'console.permissions.delete',

            // Role Management (Console)
            'console.roles.view',
            'console.roles.create',
            'console.roles.edit',
            'console.roles.delete',

            // Analytics & Reports
            'console.analytics.view',
            'console.analytics.export',

            // Support & Impersonation
            'support.impersonate',
            'support.tenants.read',
            'support.tenants.manage_users',
            'support.tenants.manage_agents',
            'support.tenants.view_audit',
            'support.tenants.manage_settings',

            // System Settings
            'console.settings.view',
            'console.settings.edit',

            // Activity Logs
            'console.logs.view',
            'console.logs.export',
        ];

        foreach ($consolePermissions as $permission) {
            \Spatie\Permission\Models\Permission::create([
                'name' => $permission,
                'guard_name' => 'console',
            ]);
        }

        // =====================================================================
        // TENANT PERMISSIONS (guard_name = 'tenant')
        // For Tenant Dashboard - Catalog managed by Obsolio
        // Tenants create custom roles using these permissions
        // =====================================================================
        $tenantPermissions = [
            // Dashboard
            'tenant.dashboard.view',

            // Profile Management
            'tenant.profile.view',
            'tenant.profile.edit',

            // Users Management (within tenant)
            'tenant.users.view',
            'tenant.users.invite',
            'tenant.users.edit',
            'tenant.users.remove',
            'tenant.users.manage_roles',

            // Roles Management (within tenant)
            'tenant.roles.view',
            'tenant.roles.create',
            'tenant.roles.edit',
            'tenant.roles.delete',

            // Organizations (within tenant)
            'tenant.organizations.view',
            'tenant.organizations.edit',
            'tenant.organizations.settings',

            // Agents
            'tenant.agents.view',
            'tenant.agents.install',
            'tenant.agents.uninstall',
            'tenant.agents.configure',
            'tenant.agents.run',

            // Agent Runs
            'tenant.agent_runs.view',
            'tenant.agent_runs.retry',
            'tenant.agent_runs.cancel',

            // Workflows
            'tenant.workflows.view',
            'tenant.workflows.create',
            'tenant.workflows.edit',
            'tenant.workflows.delete',
            'tenant.workflows.execute',

            // Teams
            'tenant.teams.view',
            'tenant.teams.create',
            'tenant.teams.edit',
            'tenant.teams.delete',
            'tenant.teams.manage_members',

            // Projects
            'tenant.projects.view',
            'tenant.projects.create',
            'tenant.projects.edit',
            'tenant.projects.delete',

            // Departments
            'tenant.departments.view',
            'tenant.departments.create',
            'tenant.departments.edit',
            'tenant.departments.delete',

            // Branches
            'tenant.branches.view',
            'tenant.branches.create',
            'tenant.branches.edit',
            'tenant.branches.delete',

            // Subscription & Billing
            'tenant.subscription.view',
            'tenant.subscription.change_plan',
            'tenant.subscription.cancel',

            'tenant.billing.view',
            'tenant.billing.manage_payment_methods',
            'tenant.billing.view_invoices',

            // API Keys
            'tenant.api_keys.view',
            'tenant.api_keys.create',
            'tenant.api_keys.revoke',

            // Webhooks
            'tenant.webhooks.view',
            'tenant.webhooks.create',
            'tenant.webhooks.edit',
            'tenant.webhooks.delete',

            // Activity & Audit
            'tenant.activity.view',
            'tenant.activity.export',

            // Settings
            'tenant.settings.view',
            'tenant.settings.edit',
        ];

        foreach ($tenantPermissions as $permission) {
            \Spatie\Permission\Models\Permission::create([
                'name' => $permission,
                'guard_name' => 'tenant',
            ]);
        }

        // =====================================================================
        // CONSOLE ROLES (guard_name = 'console', tenant_id = NULL)
        // =====================================================================

        // Super Admin - Full access to everything
        $superAdmin = \Spatie\Permission\Models\Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'console',
            'tenant_id' => null,
        ]);
        $superAdmin->givePermissionTo(\Spatie\Permission\Models\Permission::where('guard_name', 'console')->get());

        // Admin - Most permissions except system-critical ones
        $admin = \Spatie\Permission\Models\Role::create([
            'name' => 'Admin',
            'guard_name' => 'console',
            'tenant_id' => null,
        ]);
        $admin->givePermissionTo([
            'console.tenants.view',
            'console.tenants.edit',
            'console.users.view',
            'console.users.edit',
            'console.subscriptions.view',
            'console.subscriptions.edit',
            'console.analytics.view',
            'console.logs.view',
        ]);

        // Support - Limited to support/impersonation
        $support = \Spatie\Permission\Models\Role::create([
            'name' => 'Support',
            'guard_name' => 'console',
            'tenant_id' => null,
        ]);
        $support->givePermissionTo([
            'support.impersonate',
            'support.tenants.read',
            'support.tenants.manage_users',
            'support.tenants.manage_agents',
            'support.tenants.view_audit',
            'console.tenants.view',
            'console.users.view',
        ]);

        // Analyst - Read-only analytics
        $analyst = \Spatie\Permission\Models\Role::create([
            'name' => 'Analyst',
            'guard_name' => 'console',
            'tenant_id' => null,
        ]);
        $analyst->givePermissionTo([
            'console.tenants.view',
            'console.analytics.view',
            'console.analytics.export',
            'console.logs.view',
        ]);

        $this->command->info('✅ Permission catalogs seeded successfully!');
        $this->command->info('   - Console permissions: ' . count($consolePermissions));
        $this->command->info('   - Tenant permissions: ' . count($tenantPermissions));
        $this->command->info('   - Console roles: 4 (Super Admin, Admin, Support, Analyst)');
    }
}

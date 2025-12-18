<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Organization;
use Illuminate\Support\Str;

class EnsureOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find tenants that have no organizations
        $tenants = Tenant::doesntHave('organizations')->get();

        foreach ($tenants as $tenant) {
            $this->command->info("Creating default organization for tenant: {$tenant->id}");

            // Create default organization
            Organization::create([
                'tenant_id' => $tenant->id,
                'name' => $tenant->organization_name ?? $tenant->name ?? 'Default Organization',
                'short_name' => $tenant->short_name ?? $tenant->slug ?? Str::slug($tenant->name),
                'settings' => [],
            ]);
        }
    }
}

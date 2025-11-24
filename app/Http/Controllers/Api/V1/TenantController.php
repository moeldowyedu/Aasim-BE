<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * Display the current tenant.
     */
    public function show(): TenantResource
    {
        $tenant = tenant();

        // Ensure we have a Tenant model instance
        if (!($tenant instanceof Tenant)) {
            $tenant = Tenant::find($tenant->id);
        }

        return new TenantResource($tenant);
    }

    /**
     * Update the current tenant.
     */
    public function update(Request $request): TenantResource
    {
        $tenant = tenant();

        // Ensure we have a Tenant model instance
        if (!($tenant instanceof Tenant)) {
            $tenant = Tenant::find($tenant->id);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'short_name' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tenants', 'short_name')->ignore($tenant->id)
            ],
        ]);

        $tenant->update($validated);

        return new TenantResource($tenant);
    }
}

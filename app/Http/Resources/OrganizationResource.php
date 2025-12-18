<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'industry' => $this->industry,
            'company_size' => $this->company_size,
            'country' => $this->country,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'logo_url' => $this->logo_url,
            'description' => $this->description,
            'settings' => $this->settings,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'branches_count' => $this->whenCounted('branches'),
            'departments_count' => $this->whenCounted('departments'),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}

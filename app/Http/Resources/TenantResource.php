<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'slug' => $this->slug,
            'type' => $this->type,
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'is_on_trial' => $this->isOnTrial(),
            'user_role' => $this->whenLoaded('memberships', function () {
                return $this->memberships->first()?->role;
            }),
            'logo_url' => $this->organizations->sortBy('created_at')->first()?->logo_url,
            'organizationLogo' => $this->organizations->sortBy('created_at')->first()?->logo_url, // Alias for frontend compatibility

            // Setup & Stats
            'subdomain' => $this->id, // Tenant ID is the subdomain
            'tenant_admin' => $this->whenLoaded('ownerMembership', function () {
                return $this->ownerMembership?->user;
            }),
            'total_users' => $this->memberships_count, // Available when withCount specified
            'days_left' => $this->trial_ends_at ? max(0, now()->diffInDays($this->trial_ends_at, false)) : 0,

            // Dates
            'start_date' => $this->created_at?->toIso8601String(),
            'end_date' => $this->trial_ends_at?->toIso8601String(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

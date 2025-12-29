<?php

namespace App\Http\Middleware;

use App\Models\ImpersonationLog;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantScopedAuthorization
{
    /**
     * Handle an incoming request.
     *
     * Resolves tenant context and validates membership/impersonation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Resolve tenant from request host/domain
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        // Check for impersonation token
        $impersonationToken = $request->header('X-Impersonation-Token');

        if ($impersonationToken) {
            $impersonation = ImpersonationLog::findByToken($impersonationToken);

            if (!$impersonation || $impersonation->tenant_id !== $tenant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired impersonation token',
                ], 403);
            }

            // Store impersonation context in request
            $request->merge([
                'impersonation_mode' => true,
                'impersonation_log_id' => $impersonation->id,
                'admin_user_id' => $impersonation->admin_user_id,
            ]);

            // In impersonation mode, authorization uses console permissions
            $request->merge(['tenant' => $tenant]);
            return $next($request);
        }

        // Check tenant membership
        $membership = TenantMembership::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', TenantMembership::STATUS_ACTIVE)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this tenant',
            ], 403);
        }

        // Store tenant context in request
        $request->merge([
            'tenant' => $tenant,
            'tenant_membership' => $membership,
            'impersonation_mode' => false,
        ]);

        return $next($request);
    }

    /**
     * Resolve tenant from request.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // Option 1: From subdomain (e.g., acme.obsolio.com)
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        $tenant = Tenant::where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();

        if ($tenant) {
            return $tenant;
        }

        // Option 2: From custom domain
        $tenant = Tenant::where('custom_domain', $host)
            ->where('status', 'active')
            ->first();

        if ($tenant) {
            return $tenant;
        }

        // Option 3: From explicit tenant_id in request (for testing/API)
        if ($request->has('tenant_id')) {
            return Tenant::where('id', $request->input('tenant_id'))
                ->where('status', 'active')
                ->first();
        }

        return null;
    }
}

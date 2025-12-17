<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddTenantHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add tenant ID to response headers if in tenant context
        if (function_exists('tenancy') && tenancy()->initialized) {
            $tenant = tenant();
            if ($tenant) {
                $response->headers->set('X-Tenant-Id', $tenant->id);
            }
        }

        return $response;
    }
}

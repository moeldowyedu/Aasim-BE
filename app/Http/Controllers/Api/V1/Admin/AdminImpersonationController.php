<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminImpersonationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/admin/tenants/{tenantId}/impersonations/start",
     *     tags={"Admin - Impersonation"},
     *     summary="Start impersonating a tenant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tenantId",
     *         in="path",
     *         required=true,
     *         description="Tenant ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Support ticket #12345"),
     *             @OA\Property(property="ttl_minutes", type="integer", example=30)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Impersonation started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Impersonation started successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="impersonation_id", type="integer"),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function start(Request $request, string $tenantId)
    {
        try {
            $user = Auth::user();

            // Check permission
            if (!$user->hasConsolePermission('support.impersonate')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Missing support.impersonate permission',
                ], 403);
            }

            // Validate tenant
            $tenant = Tenant::where('id', $tenantId)
                ->where('status', 'active')
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found or inactive',
                ], 404);
            }

            // Validate request
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
                'ttl_minutes' => 'nullable|integer|min:5|max:480',
            ]);

            $ttlMinutes = $validated['ttl_minutes'] ?? 30;
            $reason = $validated['reason'] ?? 'Support impersonation';

            // Start impersonation
            $result = ImpersonationLog::startImpersonation(
                adminUserId: $user->id,
                tenantId: $tenantId,
                reason: $reason,
                metadata: [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
                ttlMinutes: $ttlMinutes
            );

            // Log activity
            activity()
                ->causedBy($user)
                ->performedOn($tenant)
                ->withProperties([
                    'impersonation_id' => $result['log']->id,
                    'tenant_id' => $tenantId,
                    'reason' => $reason,
                    'ttl_minutes' => $ttlMinutes,
                ])
                ->log('started_impersonation');

            return response()->json([
                'success' => true,
                'message' => 'Impersonation started successfully',
                'data' => [
                    'impersonation_id' => $result['log']->id,
                    'token' => $result['token'],
                    'expires_at' => $result['log']->expires_at,
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'email' => $tenant->email,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start impersonation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/impersonations/{id}/end",
     *     tags={"Admin - Impersonation"},
     *     summary="End an active impersonation session",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Impersonation Log ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Impersonation ended successfully"
     *     )
     * )
     */
    public function end(Request $request, int $id)
    {
        try {
            $user = Auth::user();

            $impersonation = ImpersonationLog::findOrFail($id);

            // Verify admin owns this impersonation
            if ($impersonation->admin_user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You did not start this impersonation',
                ], 403);
            }

            if (!$impersonation->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impersonation is not active',
                ], 400);
            }

            $impersonation->endSession();

            // Log activity
            activity()
                ->causedBy($user)
                ->performedOn($impersonation->tenant)
                ->withProperties([
                    'impersonation_id' => $impersonation->id,
                    'tenant_id' => $impersonation->tenant_id,
                    'duration_minutes' => $impersonation->getDurationMinutes(),
                ])
                ->log('ended_impersonation');

            return response()->json([
                'success' => true,
                'message' => 'Impersonation ended successfully',
                'data' => [
                    'duration_minutes' => $impersonation->getDurationMinutes(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to end impersonation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/impersonations",
     *     tags={"Admin - Impersonation"},
     *     summary="List impersonation sessions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by status: active, ended, expired",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         required=false,
     *         description="Filter by tenant ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of impersonation sessions"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $query = ImpersonationLog::with(['admin', 'tenant'])
                ->orderBy('started_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $status = $request->input('status');
                if ($status === 'active') {
                    $query->active()->notExpired();
                } elseif ($status === 'ended') {
                    $query->whereNotNull('ended_at');
                } elseif ($status === 'expired') {
                    $query->whereNull('ended_at')
                        ->where('expires_at', '<=', now());
                }
            }

            // Filter by tenant
            if ($request->has('tenant_id')) {
                $query->byTenant($request->input('tenant_id'));
            }

            // Filter by admin (only show own unless has permission to view all)
            if (!$user->hasConsolePermission('console.logs.view')) {
                $query->byAdmin($user->id);
            }

            $impersonations = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $impersonations->items(),
                'meta' => [
                    'current_page' => $impersonations->currentPage(),
                    'total' => $impersonations->total(),
                    'per_page' => $impersonations->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve impersonations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/impersonations/{id}",
     *     tags={"Admin - Impersonation"},
     *     summary="Get impersonation session details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Impersonation Log ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Impersonation session details"
     *     )
     * )
     */
    public function show(Request $request, int $id)
    {
        try {
            $user = Auth::user();

            $impersonation = ImpersonationLog::with(['admin', 'tenant'])
                ->findOrFail($id);

            // Verify access
            if ($impersonation->admin_user_id !== $user->id && !$user->hasConsolePermission('console.logs.view')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $impersonation->id,
                    'admin' => [
                        'id' => $impersonation->admin->id,
                        'name' => $impersonation->admin->name,
                        'email' => $impersonation->admin->email,
                    ],
                    'tenant' => [
                        'id' => $impersonation->tenant->id,
                        'name' => $impersonation->tenant->name,
                        'email' => $impersonation->tenant->email,
                    ],
                    'started_at' => $impersonation->started_at,
                    'ended_at' => $impersonation->ended_at,
                    'expires_at' => $impersonation->expires_at,
                    'duration_minutes' => $impersonation->getDurationMinutes(),
                    'is_active' => $impersonation->isActive(),
                    'is_expired' => $impersonation->isExpired(),
                    'reason' => $impersonation->reason,
                    'ip_address' => $impersonation->ip_address,
                    'metadata' => $impersonation->metadata,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve impersonation details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

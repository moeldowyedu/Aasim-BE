<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantManagementController extends Controller
{
    /**
     * List all tenants with advanced filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');
        $planId = $request->query('plan_id');
        $hasSubscription = $request->query('has_subscription');
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        $query = Tenant::query()
            ->with([
                'activeSubscription.plan',
                'organization',
                'memberships'
            ])
            ->withCount('memberships');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('subdomain_preference', 'ILIKE', "%{$search}%");
            });
        }

        // Type filter
        if ($type) {
            $query->where('type', $type);
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Plan filter (via active subscription)
        if ($planId) {
            $query->whereHas('activeSubscription', function ($q) use ($planId) {
                $q->where('plan_id', $planId);
            });
        }

        // Subscription status filter
        if ($hasSubscription !== null) {
            if ($hasSubscription === 'true') {
                $query->has('activeSubscription');
            } else {
                $query->doesntHave('activeSubscription');
            }
        }

        // Sorting
        $allowedSortFields = ['created_at', 'name', 'email', 'type', 'status'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $tenants = $query->paginate($request->query('per_page', 20));

        // Add computed fields to each tenant
        $tenants->getCollection()->transform(function ($tenant) {
            $tenant->is_on_trial = $tenant->isOnTrial();
            $tenant->trial_days_remaining = $tenant->trialDaysRemaining();
            $tenant->billing_cycle = $tenant->billingCycle();
            $tenant->has_active_subscription = $tenant->hasActiveSubscription();
            return $tenant;
        });

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    /**
     * Get detailed information about a specific tenant.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with([
            'activeSubscription.plan',
            'subscriptions.plan',
            'organization',
            'organizations',
            'memberships.user',
            'invoices' => function ($query) {
                $query->latest()->limit(10);
            },
            'paymentMethods'
        ])
            ->withCount(['memberships', 'invoices', 'subscriptions'])
            ->findOrFail($id);

        // Add computed fields
        $tenant->is_on_trial = $tenant->isOnTrial();
        $tenant->trial_days_remaining = $tenant->trialDaysRemaining();
        $tenant->billing_cycle = $tenant->billingCycle();
        $tenant->has_active_subscription = $tenant->hasActiveSubscription();

        return response()->json([
            'success' => true,
            'data' => $tenant,
        ]);
    }

    /**
     * Update tenant status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending_verification', 'active', 'inactive', 'suspended'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::findOrFail($id);
        $oldStatus = $tenant->status;

        $tenant->update([
            'status' => $request->status,
        ]);

        // Log the status change
        activity()
            ->performedOn($tenant)
            ->causedBy(auth()->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'reason' => $request->reason,
            ])
            ->log('tenant_status_changed');

        return response()->json([
            'success' => true,
            'message' => 'Tenant status updated successfully',
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Change tenant's subscription plan.
     */
    public function changeSubscription(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_cycle' => ['required', Rule::in(['monthly', 'annual'])],
            'starts_immediately' => 'boolean',
            'prorate' => 'boolean',
        ]);

        $tenant = Tenant::findOrFail($id);
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
        $currentSubscription = $tenant->activeSubscription;

        try {
            DB::beginTransaction();

            // Cancel current subscription if it exists
            if ($currentSubscription) {
                $currentSubscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ends_at' => $request->starts_immediately ? now() : $currentSubscription->current_period_end,
                ]);
            }

            // Create new subscription
            $subscription = Subscription::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'plan_id' => $newPlan->id,
                'status' => $newPlan->trial_days > 0 ? 'trialing' : 'active',
                'billing_cycle' => $request->billing_cycle,
                'starts_at' => $request->starts_immediately ? now() : ($currentSubscription?->ends_at ?? now()),
                'trial_ends_at' => $newPlan->trial_days > 0 ? now()->addDays($newPlan->trial_days) : null,
                'current_period_start' => now(),
                'current_period_end' => $request->billing_cycle === 'annual' ? now()->addYear() : now()->addMonth(),
                'metadata' => [
                    'changed_by_admin' => true,
                    'admin_id' => auth()->id(),
                    'previous_plan_id' => $currentSubscription?->plan_id,
                    'prorate' => $request->prorate ?? false,
                ],
            ]);

            // Log the subscription change
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_plan_id' => $currentSubscription?->plan_id,
                    'new_plan_id' => $newPlan->id,
                    'billing_cycle' => $request->billing_cycle,
                ])
                ->log('subscription_changed_by_admin');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription changed successfully',
                'data' => [
                    'tenant' => $tenant->fresh(),
                    'subscription' => $subscription->load('plan'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to change subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription history for a tenant.
     */
    public function subscriptionHistory(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $subscriptions = $tenant->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'subscriptions' => $subscriptions,
            ],
        ]);
    }

    /**
     * Extend tenant's trial period.
     */
    public function extendTrial(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::findOrFail($id);
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found for this tenant',
            ], 404);
        }

        $oldTrialEndsAt = $subscription->trial_ends_at;
        $newTrialEndsAt = ($oldTrialEndsAt ?? now())->addDays($request->days);

        $subscription->update([
            'trial_ends_at' => $newTrialEndsAt,
            'status' => 'trialing',
        ]);

        // Log the trial extension
        activity()
            ->performedOn($tenant)
            ->causedBy(auth()->user())
            ->withProperties([
                'old_trial_ends_at' => $oldTrialEndsAt,
                'new_trial_ends_at' => $newTrialEndsAt,
                'days_added' => $request->days,
                'reason' => $request->reason,
            ])
            ->log('trial_extended_by_admin');

        return response()->json([
            'success' => true,
            'message' => "Trial extended by {$request->days} days",
            'data' => $subscription->fresh(),
        ]);
    }

    /**
     * Get tenant statistics and analytics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'by_type' => [
                'personal' => Tenant::where('type', 'personal')->count(),
                'organization' => Tenant::where('type', 'organization')->count(),
            ],
            'by_status' => Tenant::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'with_active_subscription' => Tenant::has('activeSubscription')->count(),
            'on_trial' => Subscription::where('status', 'trialing')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'subscription_by_plan' => SubscriptionPlan::withCount([
                'subscriptions' => function ($query) {
                    $query->whereIn('status', ['trialing', 'active']);
                }
            ])
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'type' => $plan->type,
                        'tier' => $plan->tier,
                        'active_subscriptions' => $plan->subscriptions_count,
                    ];
                }),
            'recent_signups' => Tenant::where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Delete a tenant (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        try {
            DB::beginTransaction();

            // Cancel active subscriptions
            $tenant->subscriptions()
                ->whereIn('status', ['trialing', 'active'])
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

            // Soft delete the tenant
            $tenant->delete();

            // Log the deletion
            activity()
                ->performedOn($tenant)
                ->causedBy(auth()->user())
                ->log('tenant_deleted_by_admin');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration moves subscription data from tenants table to subscriptions table.
     */
    public function up(): void
    {
        // Process each tenant that has plan_id
        $tenants = DB::table('tenants')
            ->whereNotNull('plan_id')
            ->get();

        foreach ($tenants as $tenant) {
            // Check if subscription already exists for this tenant
            $existingSubscription = DB::table('subscriptions')
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['trialing', 'active'])
                ->first();

            if (!$existingSubscription) {
                // Determine subscription status
                $status = 'active';
                $trialEndsAt = $tenant->trial_ends_at ?? null;

                if ($trialEndsAt && now()->parse($trialEndsAt)->isFuture()) {
                    $status = 'trialing';
                }

                // Get plan details to set trial_ends_at if not set
                $plan = DB::table('subscription_plans')
                    ->where('id', $tenant->plan_id)
                    ->first();

                // If trial_ends_at is not set and plan has trial days, set it
                if (!$trialEndsAt && $plan && $plan->trial_days > 0) {
                    $trialEndsAt = now()->addDays($plan->trial_days);
                }

                // Create subscription record
                DB::table('subscriptions')->insert([
                    'id' => Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'plan_id' => $tenant->plan_id,
                    'status' => $status,
                    'billing_cycle' => $tenant->billing_cycle ?? 'monthly',
                    'starts_at' => $tenant->created_at ?? now(),
                    'ends_at' => null,
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                    'canceled_at' => null,
                    'stripe_subscription_id' => null,
                    'stripe_customer_id' => null,
                    'metadata' => json_encode([
                        'migrated_from_tenant' => true,
                        'migration_date' => now()->toDateTimeString(),
                    ]),
                    'created_at' => $tenant->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                echo "✅ Created subscription for tenant: {$tenant->id}\n";
            } else {
                echo "ℹ️  Subscription already exists for tenant: {$tenant->id}\n";
            }
        }

        // Link tenants to their organizations (if organization type)
        $orgTenants = DB::table('tenants')
            ->where('type', 'organization')
            ->whereNull('organization_id')
            ->get();

        foreach ($orgTenants as $tenant) {
            // Find organization by tenant_id
            $organization = DB::table('organizations')
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($organization) {
                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['organization_id' => $organization->id]);

                echo "✅ Linked tenant {$tenant->id} to organization {$organization->id}\n";
            }
        }

        echo "✅ Data migration completed successfully!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove subscriptions that were created by this migration
        DB::table('subscriptions')
            ->whereJsonContains('metadata->migrated_from_tenant', true)
            ->delete();

        echo "✅ Rolled back migrated subscriptions\n";
    }
};

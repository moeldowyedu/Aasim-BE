<?php

namespace App\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class ResetUsageQuotasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting usage quota reset");

        // Get all active subscriptions that need reset
        $subscriptions = Subscription::active()
            ->where('executions_used', '>', 0)
            ->get();

        Log::info("Found {$subscriptions->count()} subscriptions to reset");

        $resetCount = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $oldUsage = $subscription->executions_used;

                // Reset monthly usage
                $subscription->resetMonthlyUsage();

                Log::info("Usage quota reset", [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'previous_usage' => $oldUsage,
                    'new_quota' => $subscription->execution_quota,
                ]);

                $resetCount++;

            } catch (\Exception $e) {
                Log::error("Failed to reset usage quota", [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Usage quota reset completed", [
            'reset_count' => $resetCount,
        ]);
    }
}

<?php

namespace App\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class HandleExpiredSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting expired subscription handling");

        // Get subscriptions that have expired
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('auto_renew', false)
            ->whereDate('current_period_end', '<', now())
            ->with(['tenant'])
            ->get();

        Log::info("Found {$expiredSubscriptions->count()} expired subscriptions");

        $deactivatedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            try {
                Log::info("Deactivating expired subscription", [
                    'subscription_id' => $subscription->id,
                    'tenant' => $subscription->tenant->name ?? $subscription->tenant->id,
                    'expired_date' => $subscription->current_period_end,
                ]);

                // Deactivate subscription
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

                // Deactivate all agent subscriptions
                $subscription->tenant->agentSubscriptions()
                    ->active()
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'auto_renew' => false,
                    ]);

                // TODO: Send expiration notification email

                $deactivatedCount++;

            } catch (\Exception $e) {
                Log::error("Failed to handle expired subscription", [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Expired subscription handling completed", [
            'deactivated_count' => $deactivatedCount,
        ]);
    }
}

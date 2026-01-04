<?php

namespace App\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AgentSubscription;
use Illuminate\Support\Facades\Log;

class RenewAgentSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting agent subscriptions renewal");

        // Get all agent subscriptions due for renewal
        $subscriptions = AgentSubscription::dueForRenewal()
            ->with(['tenant', 'agent'])
            ->get();

        Log::info("Found {$subscriptions->count()} agent subscriptions to renew");

        $successCount = 0;
        $failureCount = 0;

        foreach ($subscriptions as $agentSub) {
            try {
                // Renew the subscription
                $agentSub->renew();

                Log::info("Agent subscription renewed", [
                    'subscription_id' => $agentSub->id,
                    'tenant_id' => $agentSub->tenant_id,
                    'agent_id' => $agentSub->agent_id,
                    'next_billing_date' => $agentSub->next_billing_date,
                ]);

                $successCount++;

            } catch (\Exception $e) {
                $failureCount++;

                Log::error("Failed to renew agent subscription", [
                    'subscription_id' => $agentSub->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Agent subscription renewal completed", [
            'success' => $successCount,
            'failed' => $failureCount,
        ]);
    }
}

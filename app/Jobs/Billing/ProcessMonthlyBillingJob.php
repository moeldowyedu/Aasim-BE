<?php

namespace App\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessMonthlyBillingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $year;
    protected $month;

    /**
     * Create a new job instance.
     */
    public function __construct($year = null, $month = null)
    {
        $this->year = $year ?? now()->year;
        $this->month = $month ?? now()->month;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting monthly billing process", [
            'year' => $this->year,
            'month' => $this->month,
        ]);

        // Get all active subscriptions due for renewal
        $subscriptions = Subscription::active()
            ->dueForRenewal()
            ->with(['tenant', 'plan.billingCycle'])
            ->get();

        Log::info("Found {$subscriptions->count()} subscriptions to bill");

        $successCount = 0;
        $failureCount = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->processSingleSubscription($subscription);
                $successCount++;
            } catch (\Exception $e) {
                $failureCount++;
                Log::error("Failed to process subscription billing", [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Monthly billing completed", [
            'success' => $successCount,
            'failed' => $failureCount,
        ]);
    }

    /**
     * Process billing for a single subscription
     */
    protected function processSingleSubscription(Subscription $subscription)
    {
        DB::beginTransaction();

        try {
            $tenant = $subscription->tenant;
            $plan = $subscription->plan;

            // Define billing period
            $periodStart = Carbon::create($this->year, $this->month, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            Log::info("Processing subscription", [
                'subscription_id' => $subscription->id,
                'tenant' => $tenant->name ?? $tenant->id,
                'plan' => $plan->name,
            ]);

            // Create invoice
            $invoice = Invoice::createForTenant(
                $tenant,
                $periodStart,
                $periodEnd,
                $subscription
            );

            // Add base subscription line item
            $monthlyPrice = $plan->getMonthlyEquivalentPrice();
            InvoiceLineItem::createBasePlan($invoice, $plan, $monthlyPrice);

            // Add agent add-ons
            $agentSubscriptions = $tenant->activeAgentSubscriptions;
            foreach ($agentSubscriptions as $agentSub) {
                InvoiceLineItem::createAgentAddon(
                    $invoice,
                    $agentSub->agent,
                    $agentSub->monthly_price,
                    $periodEnd->day
                );
            }

            // Add usage overage
            $overageExecutions = max(0, $subscription->executions_used - $subscription->execution_quota);
            if ($overageExecutions > 0 && $plan->overage_price_per_execution) {
                InvoiceLineItem::createUsageOverage(
                    $invoice,
                    $overageExecutions,
                    $plan->overage_price_per_execution
                );
            }

            // Recalculate total
            $invoice->recalculateTotal();

            Log::info("Invoice created", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total' => $invoice->total_amount,
            ]);

            // Update subscription billing dates
            $billingCycle = $plan->billingCycle;
            $subscription->update([
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd->copy()->addMonths($billingCycle->months),
                'next_billing_date' => $periodEnd->copy()->addMonths($billingCycle->months),
            ]);

            DB::commit();

            // TODO: Send invoice to payment gateway (Paymob)
            // TODO: Send invoice email to customer

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

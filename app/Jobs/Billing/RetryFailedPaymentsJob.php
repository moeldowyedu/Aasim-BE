<?php

namespace App\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class RetryFailedPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting failed payment retries");

        // Get failed invoices that are eligible for retry
        // Retry on day 1, 3, and 7 after failure
        $failedInvoices = Invoice::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['tenant', 'subscription'])
            ->get();

        Log::info("Found {$failedInvoices->count()} failed invoices");

        $retryCount = 0;
        $successCount = 0;

        foreach ($failedInvoices as $invoice) {
            try {
                $daysSinceFailed = now()->diffInDays($invoice->created_at);

                // Retry on specific days
                if (in_array($daysSinceFailed, [1, 3, 7])) {

                    Log::info("Retrying failed payment", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'days_since_failed' => $daysSinceFailed,
                    ]);

                    // TODO: Retry payment with Paymob
                    // $result = PaymobService::retryPayment($invoice);

                    // if ($result['success']) {
                    //     $invoice->markAsPaid($result['transaction_id']);
                    //     $successCount++;
                    // }

                    $retryCount++;
                }

            } catch (\Exception $e) {
                Log::error("Failed to retry payment", [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Failed payment retries completed", [
            'retry_count' => $retryCount,
            'success_count' => $successCount,
        ]);
    }
}

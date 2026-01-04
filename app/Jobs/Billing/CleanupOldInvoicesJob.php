<?php

namespace App\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class CleanupOldInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting old invoices cleanup");

        // Archive invoices older than 2 years
        $archiveDate = now()->subYears(2);

        $oldInvoices = Invoice::whereIn('status', ['paid', 'cancelled', 'refunded'])
            ->where('created_at', '<', $archiveDate)
            ->get();

        Log::info("Found {$oldInvoices->count()} old invoices to archive");

        // TODO: Implement archiving logic
        // For now, just log

        foreach ($oldInvoices as $invoice) {
            Log::info("Invoice eligible for archiving", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'created_at' => $invoice->created_at,
                'status' => $invoice->status,
            ]);
        }

        Log::info("Old invoices cleanup completed");
    }
}

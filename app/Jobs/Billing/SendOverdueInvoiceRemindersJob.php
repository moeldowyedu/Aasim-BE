<?php

namespace App\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class SendOverdueInvoiceRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting overdue invoice reminders");

        // Get overdue invoices
        $overdueInvoices = Invoice::overdue()
            ->with(['tenant', 'subscription.plan'])
            ->get();

        Log::info("Found {$overdueInvoices->count()} overdue invoices");

        $sentCount = 0;

        foreach ($overdueInvoices as $invoice) {
            try {
                $daysOverdue = $invoice->getDaysOverdue();

                // Send reminder based on days overdue
                if (in_array($daysOverdue, [1, 3, 7, 14, 30])) {

                    Log::info("Sending overdue reminder", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'days_overdue' => $daysOverdue,
                        'tenant' => $invoice->tenant->name ?? $invoice->tenant->id,
                    ]);

                    // TODO: Send email reminder
                    // Mail::to($invoice->tenant->email)
                    //     ->send(new OverdueInvoiceReminder($invoice, $daysOverdue));

                    $sentCount++;
                }

            } catch (\Exception $e) {
                Log::error("Failed to send overdue reminder", [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Overdue invoice reminders completed", [
            'sent_count' => $sentCount,
        ]);
    }
}

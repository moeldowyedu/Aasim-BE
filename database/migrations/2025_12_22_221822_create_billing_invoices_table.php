<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('subscription_id')->nullable();
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['draft', 'pending', 'paid', 'failed', 'void', 'refunded'])->default('pending');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('invoice_pdf_url', 500)->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->jsonb('line_items')->default('[]');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('set null');

            // Indexes
            $table->index('tenant_id');
            $table->index('subscription_id');
            $table->index('status');
            $table->index('invoice_number');
            $table->index('stripe_invoice_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
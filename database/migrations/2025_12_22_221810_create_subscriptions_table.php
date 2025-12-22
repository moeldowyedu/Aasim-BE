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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('plan_id');
            $table->enum('status', ['trialing', 'active', 'past_due', 'canceled', 'suspended'])->default('trialing');
            $table->enum('billing_cycle', ['monthly', 'annual'])->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('plan_id')
                ->references('id')
                ->on('subscription_plans')
                ->onDelete('restrict');

            // Indexes
            $table->index('tenant_id');
            $table->index('plan_id');
            $table->index('status');
            $table->index('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
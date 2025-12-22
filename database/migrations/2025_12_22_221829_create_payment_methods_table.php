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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->enum('type', ['card', 'bank_account', 'paypal'])->default('card');
            $table->string('stripe_payment_method_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('last4', 4)->nullable();
            $table->string('brand')->nullable();
            $table->integer('exp_month')->nullable();
            $table->integer('exp_year')->nullable();
            $table->string('country', 2)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Indexes
            $table->index('tenant_id');
            $table->index('is_default');
            $table->index(['tenant_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->enum('type', ['personal', 'organization']);
            $table->enum('tier', ['free', 'pro', 'team', 'business', 'enterprise']);
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_annual', 10, 2)->nullable();
            $table->jsonb('features')->default('{}');
            $table->jsonb('limits')->default('{}');
            $table->integer('max_users')->default(1);
            $table->integer('max_agents')->default(0);
            $table->integer('storage_gb')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('trial_days')->default(7);
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['type', 'tier']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
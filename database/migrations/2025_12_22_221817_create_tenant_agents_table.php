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
        Schema::create('tenant_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('agent_id');
            $table->enum('status', ['active', 'inactive', 'expired', 'suspended'])->default('active');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);
            $table->jsonb('configuration')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('cascade');

            // Unique Constraint
            $table->unique(['tenant_id', 'agent_id']);

            // Indexes
            $table->index('tenant_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_agents');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration adds new columns to the agents table:
     * - Adds columns: runtime_type (nullable), execution_timeout_ms
     *
     * Note: Old columns (category, total_installs, etc.) are NOT removed here.
     * They will be removed in migration 2025_12_27_130000_finalize_agents_table_changes.php
     * AFTER the data migration reads from them.
     */
    public function up(): void
    {
        // Add new columns (runtime_type is nullable for now)
        Schema::table('agents', function (Blueprint $table) {
            // Check if columns don't already exist
            if (!Schema::hasColumn('agents', 'runtime_type')) {
                $table->string('runtime_type')
                    ->nullable() // Will be made NOT NULL after data migration
                    ->after('created_by_user_id')
                    ->comment('Runtime environment: n8n | custom');
            }

            if (!Schema::hasColumn('agents', 'execution_timeout_ms')) {
                $table->integer('execution_timeout_ms')
                    ->default(30000)
                    ->after('created_by_user_id')
                    ->comment('Maximum execution time in milliseconds (default: 30 seconds)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Remove the new columns
            $table->dropColumn(['runtime_type', 'execution_timeout_ms']);

            // Restore the old columns
            $table->string('category', 100)->after('slug');
            $table->integer('total_installs')->default(0)->after('version');
            $table->decimal('rating', 3, 2)->default(0)->after('total_installs');
            $table->integer('review_count')->default(0)->after('rating');
            $table->boolean('is_marketplace')->default(true)->after('annual_price');

            // Restore indexes
            $table->index('category');
            $table->index('is_marketplace');
            $table->index(['category', 'is_active']);
        });
    }
};

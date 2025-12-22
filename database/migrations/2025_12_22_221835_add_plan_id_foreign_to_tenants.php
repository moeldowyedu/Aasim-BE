<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Create temporary UUID column
        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('plan_id_temp')->nullable();
        });

        // Step 2: Copy data if any exists and is valid UUID format
        DB::statement("
            UPDATE tenants 
            SET plan_id_temp = plan_id::uuid 
            WHERE plan_id IS NOT NULL 
            AND plan_id ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'
        ");

        // Step 3: Drop old varchar plan_id column
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });

        // Step 4: Rename temporary column to plan_id
        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('plan_id_temp', 'plan_id');
        });

        // Step 5: Add foreign key constraint
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('subscription_plans')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['plan_id']);

            // Drop UUID column
            $table->dropColumn('plan_id');
        });

        // Restore original varchar column
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan_id')->nullable();
        });
    }
};
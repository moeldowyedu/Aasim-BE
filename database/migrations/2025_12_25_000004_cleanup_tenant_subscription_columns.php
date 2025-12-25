<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove redundant subscription-related columns from tenants table.
     * This should only run after data has been migrated to subscriptions table.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Drop foreign key constraint for plan_id first
            if (Schema::hasColumn('tenants', 'plan_id')) {
                // Check if the foreign key exists before dropping
                $foreignKeys = DB::select("
                    SELECT constraint_name
                    FROM information_schema.table_constraints
                    WHERE table_name = 'tenants'
                    AND constraint_type = 'FOREIGN KEY'
                    AND constraint_name LIKE '%plan_id%'
                ");

                foreach ($foreignKeys as $fk) {
                    $table->dropForeign([$fk->constraint_name]);
                }

                $table->dropColumn('plan_id');
            }

            // Drop billing_cycle column
            if (Schema::hasColumn('tenants', 'billing_cycle')) {
                $table->dropColumn('billing_cycle');
            }

            // Drop trial_ends_at column (now managed in subscriptions)
            if (Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
        });

        echo "✅ Removed redundant subscription columns from tenants table\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Restore plan_id
            if (!Schema::hasColumn('tenants', 'plan_id')) {
                $table->uuid('plan_id')->nullable();

                $table->foreign('plan_id')
                    ->references('id')
                    ->on('subscription_plans')
                    ->onDelete('set null');
            }

            // Restore billing_cycle
            if (!Schema::hasColumn('tenants', 'billing_cycle')) {
                $table->string('billing_cycle')->nullable();
            }

            // Restore trial_ends_at
            if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }
        });

        // Restore data from subscriptions table
        $subscriptions = DB::table('subscriptions')
            ->whereIn('status', ['trialing', 'active'])
            ->get();

        foreach ($subscriptions as $subscription) {
            DB::table('tenants')
                ->where('id', $subscription->tenant_id)
                ->update([
                    'plan_id' => $subscription->plan_id,
                    'billing_cycle' => $subscription->billing_cycle,
                    'trial_ends_at' => $subscription->trial_ends_at,
                ]);
        }

        echo "✅ Restored subscription columns to tenants table\n";
    }
};

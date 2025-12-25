<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Plan publishing control
            if (!Schema::hasColumn('subscription_plans', 'is_published')) {
                $table->boolean('is_published')->default(true)->after('is_active');
            }

            // Plan archiving (soft delete without deleting the record)
            if (!Schema::hasColumn('subscription_plans', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_published');
            }

            // Plan versioning
            if (!Schema::hasColumn('subscription_plans', 'plan_version')) {
                $table->string('plan_version', 20)->default('1.0.0')->after('is_archived');
            }

            // Parent plan ID for versioning (tracks plan lineage)
            if (!Schema::hasColumn('subscription_plans', 'parent_plan_id')) {
                $table->uuid('parent_plan_id')->nullable()->after('plan_version');

                $table->foreign('parent_plan_id')
                    ->references('id')
                    ->on('subscription_plans')
                    ->onDelete('set null');
            }

            // Display order for admin UI
            if (!Schema::hasColumn('subscription_plans', 'display_order')) {
                $table->integer('display_order')->default(0)->after('parent_plan_id');
            }

            // Marketing fields
            if (!Schema::hasColumn('subscription_plans', 'highlight_features')) {
                $table->jsonb('highlight_features')->default('[]')->after('features');
            }

            if (!Schema::hasColumn('subscription_plans', 'metadata')) {
                $table->jsonb('metadata')->default('{}')->after('limits');
            }

            // Add indexes
            $table->index('is_published');
            $table->index('is_archived');
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('subscription_plans', 'parent_plan_id')) {
                $table->dropForeign(['parent_plan_id']);
            }

            // Drop indexes
            $table->dropIndex(['is_published']);
            $table->dropIndex(['is_archived']);
            $table->dropIndex(['display_order']);

            // Drop columns
            $columns = [
                'is_published',
                'is_archived',
                'plan_version',
                'parent_plan_id',
                'display_order',
                'highlight_features',
                'metadata'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('subscription_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration finalizes the agents table changes:
     * 1. Makes runtime_type NOT NULL (after data migration populated values)
     * 2. Removes old columns that are no longer needed
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // STEP 1: Make runtime_type NOT NULL now that all agents have a value
            $table->string('runtime_type')->nullable(false)->change();
        });

        // STEP 2: Remove old columns (after data migration has read from them)
        Schema::table('agents', function (Blueprint $table) {
            // Drop indexes first if they exist (using DB::statement for safety)
            try {
                if (Schema::hasColumn('agents', 'category')) {
                    DB::statement('DROP INDEX IF EXISTS agents_category_index');
                    DB::statement('DROP INDEX IF EXISTS agents_category_is_active_index');
                }
                if (Schema::hasColumn('agents', 'is_marketplace')) {
                    DB::statement('DROP INDEX IF EXISTS agents_is_marketplace_index');
                }
            } catch (\Exception $e) {
                // Indexes might not exist, that's okay
            }

            // Remove columns that are no longer needed
            $columns_to_drop = [];
            if (Schema::hasColumn('agents', 'category')) $columns_to_drop[] = 'category';
            if (Schema::hasColumn('agents', 'total_installs')) $columns_to_drop[] = 'total_installs';
            if (Schema::hasColumn('agents', 'rating')) $columns_to_drop[] = 'rating';
            if (Schema::hasColumn('agents', 'review_count')) $columns_to_drop[] = 'review_count';
            if (Schema::hasColumn('agents', 'is_marketplace')) $columns_to_drop[] = 'is_marketplace';

            if (!empty($columns_to_drop)) {
                $table->dropColumn($columns_to_drop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Make runtime_type nullable again
            $table->string('runtime_type')->nullable()->change();

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

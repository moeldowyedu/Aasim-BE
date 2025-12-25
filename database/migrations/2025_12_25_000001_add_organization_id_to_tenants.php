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
        Schema::table('tenants', function (Blueprint $table) {
            // Add organization_id foreign key
            // Nullable because personal tenants don't have organizations
            if (!Schema::hasColumn('tenants', 'organization_id')) {
                $table->uuid('organization_id')->nullable()->after('id');

                // Add foreign key constraint
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->onUpdate('cascade')
                    ->onDelete('set null');

                // Add index for better query performance
                $table->index('organization_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropIndex(['organization_id']);
                $table->dropColumn('organization_id');
            }
        });
    }
};

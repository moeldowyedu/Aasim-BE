<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Add subdomain preference field
            if (!Schema::hasColumn('tenants', 'subdomain_preference')) {
                $table->string('subdomain_preference', 63)->nullable()->after('id');
            }

            // Add activation timestamp
            if (!Schema::hasColumn('tenants', 'subdomain_activated_at')) {
                $table->timestamp('subdomain_activated_at')->nullable()->after('subdomain_preference');
            }

            // Drop old status column if it exists
            if (Schema::hasColumn('tenants', 'status')) {
                $table->dropColumn('status');
            }
        });

        // Re-add status with new enum values
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'status')) {
                $table->enum('status', ['pending_verification', 'active', 'inactive', 'suspended'])
                    ->default('pending_verification')
                    ->after('subdomain_activated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subdomain_preference', 'subdomain_activated_at', 'status']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
        });
    }
};

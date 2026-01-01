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
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'short_name')) {
                $table->string('short_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('organizations', 'phone')) {
                $table->string('phone')->nullable()->after('country');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'short_name')) {
                $table->dropColumn('short_name');
            }
            if (Schema::hasColumn('organizations', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};

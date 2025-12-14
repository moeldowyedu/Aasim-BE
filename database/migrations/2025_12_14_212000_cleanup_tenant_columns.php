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
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['setup_completed', 'setup_completed_at', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('setup_completed')->default(true);
            $table->timestamp('setup_completed_at')->nullable();
            $table->string('slug')->nullable()->unique();
        });
    }
};

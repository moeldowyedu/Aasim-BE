<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration creates the agent_categories table for hierarchical category management.
     * - Supports unlimited nesting (parent → children)
     * - One category can have many subcategories
     * - Categories can be activated/deactivated independently
     */
    public function up(): void
    {
        // Create table without foreign key first
        Schema::create('agent_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->comment('Display name of the category');
            $table->string('slug')->unique()->comment('URL-friendly identifier');
            $table->uuid('parent_id')->nullable()->comment('Parent category for hierarchical structure');
            $table->boolean('is_active')->default(true)->comment('Whether this category is visible');
            $table->timestamps();

            // Indexes for performance
            $table->index('parent_id');
            $table->index('is_active');
            $table->index(['parent_id', 'is_active']);
        });

        // Add self-referencing foreign key after table is created
        Schema::table('agent_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('agent_categories')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_categories');
    }
};

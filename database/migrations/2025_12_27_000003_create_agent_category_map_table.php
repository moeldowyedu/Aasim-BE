<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration creates the agent_category_map pivot table.
     * - Many-to-many relationship between agents and categories
     * - One agent can belong to multiple categories
     * - One category can contain multiple agents
     */
    public function up(): void
    {
        Schema::create('agent_category_map', function (Blueprint $table) {
            $table->uuid('agent_id')->comment('Reference to the agent');
            $table->uuid('category_id')->comment('Reference to the category');

            // Composite primary key ensures an agent can't be assigned to the same category twice
            $table->primary(['agent_id', 'category_id']);

            // Foreign keys with cascade delete
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('cascade')
                ->comment('Delete all category mappings when agent is deleted');

            $table->foreign('category_id')
                ->references('id')
                ->on('agent_categories')
                ->onDelete('cascade')
                ->comment('Delete all agent mappings when category is deleted');

            // Indexes for efficient queries
            $table->index('agent_id');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_category_map');
    }
};

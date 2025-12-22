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
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 100);
            $table->text('description')->nullable();
            $table->text('long_description')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->jsonb('capabilities')->default('{}');
            $table->jsonb('supported_languages')->default('["en"]');
            $table->enum('price_model', ['free', 'one_time', 'subscription', 'usage_based'])->default('free');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('annual_price', 10, 2)->nullable();
            $table->boolean('is_marketplace')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('version', 20)->default('1.0.0');
            $table->integer('total_installs')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->index('category');
            $table->index('is_marketplace');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
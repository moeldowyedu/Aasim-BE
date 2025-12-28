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
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->string('tenant_id');
            $table->uuid('user_id');
            $table->enum('status', ['active', 'invited', 'suspended', 'left'])->default('active');
            $table->string('role')->nullable()->comment('Legacy role field - use Spatie roles instead');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Primary key: composite
            $table->primary(['tenant_id', 'user_id']);

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes
            $table->index('status');
            $table->index('user_id');
        });

        // Backfill existing data from users.tenant_id
        DB::statement("
            INSERT INTO tenant_memberships (tenant_id, user_id, status, joined_at, created_at, updated_at)
            SELECT
                tenant_id,
                id as user_id,
                'active' as status,
                created_at as joined_at,
                NOW() as created_at,
                NOW() as updated_at
            FROM users
            WHERE tenant_id IS NOT NULL
            ON CONFLICT (tenant_id, user_id) DO NOTHING
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};

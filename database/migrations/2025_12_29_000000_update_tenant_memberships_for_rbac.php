<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Updates tenant_memberships table from simple role-based structure
     * to comprehensive RBAC system with status tracking.
     */
    public function up(): void
    {
        // Step 1: Add new columns to existing table
        Schema::table('tenant_memberships', function (Blueprint $table) {
            // Add status column (map existing roles to 'active')
            $table->enum('status', ['active', 'invited', 'suspended', 'left'])
                ->default('active')
                ->after('user_id');

            // Make role nullable and change to string for Spatie compatibility
            $table->string('role_temp')->nullable()->after('status');

            // Add new timestamp columns
            $table->timestamp('invited_at')->nullable()->after('joined_at');
            $table->timestamp('left_at')->nullable()->after('invited_at');

            // Add metadata
            $table->json('metadata')->nullable()->after('left_at');
        });

        // Step 2: Migrate existing role data to new role_temp column
        DB::statement("UPDATE tenant_memberships SET role_temp = role");

        // Step 3: Drop old role enum column and rename role_temp to role
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        DB::statement("ALTER TABLE tenant_memberships RENAME COLUMN role_temp TO role");

        // Step 4: Backfill from users.tenant_id (for any users not yet in memberships)
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

        // Step 5: Change primary key from 'id' to composite [tenant_id, user_id]
        Schema::table('tenant_memberships', function (Blueprint $table) {
            // Drop the old id primary key
            $table->dropPrimary(['id']);

            // Add composite primary key
            $table->primary(['tenant_id', 'user_id']);

            // Drop the id column entirely
            $table->dropColumn('id');
        });

        // Step 6: Add index on status
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add back id column and make it primary key
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->id()->first();
        });

        // Step 2: Drop composite primary key
        DB::statement('ALTER TABLE tenant_memberships DROP CONSTRAINT tenant_memberships_pkey');

        // Step 3: Restore old enum role column
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->enum('role_old', ['owner', 'admin', 'member'])->default('member');
        });

        // Migrate data back
        DB::statement("
            UPDATE tenant_memberships
            SET role_old = CASE
                WHEN role IN ('owner', 'admin', 'member') THEN role::text
                ELSE 'member'
            END
        ");

        // Step 4: Drop new columns
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'role', 'invited_at', 'left_at', 'metadata']);
        });

        // Step 5: Rename role_old back to role
        DB::statement("ALTER TABLE tenant_memberships RENAME COLUMN role_old TO role");
    }
};

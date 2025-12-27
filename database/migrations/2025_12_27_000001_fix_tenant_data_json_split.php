<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix tenants where status is default 'pending_verification' but JSON says 'active'
        DB::statement("
            UPDATE tenants 
            SET status = data->>'status' 
            WHERE status = 'pending_verification' 
            AND data->>'status' IS NOT NULL 
            AND data->>'status' != 'pending_verification'
        ");

        // Fix name
        DB::statement("
            UPDATE tenants 
            SET name = data->>'name' 
            WHERE (name IS NULL OR name = '') 
            AND data->>'name' IS NOT NULL
        ");

        // Fix short_name
        DB::statement("
            UPDATE tenants 
            SET short_name = data->>'short_name' 
            WHERE (short_name IS NULL OR short_name = '') 
            AND data->>'short_name' IS NOT NULL
        ");

        // Fix subdomain_activated_at
        DB::statement("
            UPDATE tenants 
            SET subdomain_activated_at = (data->>'subdomain_activated_at')::timestamp 
            WHERE subdomain_activated_at IS NULL 
            AND data->>'subdomain_activated_at' IS NOT NULL
        ");

        // Fix subdomain_preference
        DB::statement("
            UPDATE tenants 
            SET subdomain_preference = data->>'subdomain_preference' 
            WHERE (subdomain_preference IS NULL OR subdomain_preference = '') 
            AND data->>'subdomain_preference' IS NOT NULL
        ");

        // Fix type
        DB::statement("
            UPDATE tenants 
            SET type = data->>'type' 
            WHERE (type IS NULL OR type = '') 
            AND data->>'type' IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed, this is a data fix
    }
};

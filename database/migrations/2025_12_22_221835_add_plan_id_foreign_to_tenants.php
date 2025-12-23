<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if plan_id is already UUID type
        $result = DB::select("
            SELECT data_type 
            FROM information_schema.columns 
            WHERE table_name = 'tenants' 
            AND column_name = 'plan_id'
        ");

        $currentType = $result[0]->data_type ?? null;

        if ($currentType === 'character varying' || $currentType === 'varchar') {
            // Convert VARCHAR to UUID using raw SQL
            DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS plan_id');
            DB::statement('ALTER TABLE tenants ADD COLUMN plan_id UUID');
        }

        // Add foreign key constraint
        DB::statement('
            ALTER TABLE tenants 
            ADD CONSTRAINT tenants_plan_id_foreign 
            FOREIGN KEY (plan_id) 
            REFERENCES subscription_plans(id) 
            ON DELETE SET NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_plan_id_foreign');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS plan_id');
        DB::statement('ALTER TABLE tenants ADD COLUMN plan_id VARCHAR(255)');
    }
};
<?php

/**
 * Script to verify and run agent_tiers related migrations
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

echo "\n";
echo "==============================================\n";
echo "  OBSOLIO Agent Migrations Verification\n";
echo "==============================================\n\n";

// Check if vendor directory exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ ERROR: Composer dependencies not installed\n";
    echo "   Run: composer install\n\n";
    exit(1);
}

echo "1. Checking database connection...\n";
try {
    DB::connection()->getPdo();
    echo "   ✅ Database connection successful\n\n";
} catch (\Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "2. Checking migration status...\n";

// Check which migrations have been run
$migrations = [
    '2026_01_04_140244_create_agent_tiers_table',
    '2026_01_04_140259_add_tier_id_to_agents_table',
    '2026_01_04_140302_create_agent_pricing_table',
];

$migrationStatus = [];

foreach ($migrations as $migration) {
    $exists = DB::table('migrations')
        ->where('migration', $migration)
        ->exists();

    $migrationStatus[$migration] = $exists;

    if ($exists) {
        echo "   ✅ $migration - APPLIED\n";
    } else {
        echo "   ❌ $migration - NOT APPLIED\n";
    }
}

echo "\n";

// Check if tables exist
echo "3. Checking table existence...\n";

$tables = [
    'agent_tiers' => Schema::hasTable('agent_tiers'),
    'agent_pricing' => Schema::hasTable('agent_pricing'),
];

// Check if tier_id column exists on agents table
$hasTierIdColumn = Schema::hasColumn('agents', 'tier_id');

echo "   agent_tiers table: " . ($tables['agent_tiers'] ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "   agent_pricing table: " . ($tables['agent_pricing'] ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "   agents.tier_id column: " . ($hasTierIdColumn ? "✅ EXISTS" : "❌ MISSING") . "\n";

echo "\n";

// Determine if migrations need to be run
$needsMigration = !$tables['agent_tiers'] || !$tables['agent_pricing'] || !$hasTierIdColumn;

if ($needsMigration) {
    echo "4. Running missing migrations...\n";

    try {
        // Run specific migrations
        if (!$tables['agent_tiers']) {
            echo "   Running: create_agent_tiers_table...\n";
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_01_04_140244_create_agent_tiers_table.php',
                '--force' => true
            ]);
            echo "   ✅ agent_tiers table created\n";
        }

        if (!$hasTierIdColumn && $tables['agent_tiers']) {
            echo "   Running: add_tier_id_to_agents_table...\n";
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_01_04_140259_add_tier_id_to_agents_table.php',
                '--force' => true
            ]);
            echo "   ✅ tier_id column added to agents table\n";
        }

        if (!$tables['agent_pricing'] && $tables['agent_tiers']) {
            echo "   Running: create_agent_pricing_table...\n";
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_01_04_140302_create_agent_pricing_table.php',
                '--force' => true
            ]);
            echo "   ✅ agent_pricing table created\n";
        }

        echo "\n   ✅ All migrations completed successfully!\n\n";

    } catch (\Exception $e) {
        echo "\n   ❌ Migration failed: " . $e->getMessage() . "\n\n";
        exit(1);
    }

} else {
    echo "4. Migration status: ✅ ALL MIGRATIONS APPLIED\n\n";
}

// Check if agent_tiers table has data
echo "5. Checking agent_tiers data...\n";

if ($tables['agent_tiers'] || Schema::hasTable('agent_tiers')) {
    $tierCount = DB::table('agent_tiers')->count();

    if ($tierCount > 0) {
        echo "   ✅ Agent tiers data exists ($tierCount tiers)\n";

        $tiers = DB::table('agent_tiers')->orderBy('display_order')->get(['id', 'name', 'description']);
        foreach ($tiers as $tier) {
            echo "      - [{$tier->id}] {$tier->name}: {$tier->description}\n";
        }
    } else {
        echo "   ⚠️  Agent tiers table is empty\n";
        echo "   Run: php artisan db:seed --class=AgentTiersSeeder\n";
    }
} else {
    echo "   ❌ agent_tiers table does not exist\n";
}

echo "\n";

// Summary
echo "==============================================\n";
echo "  VERIFICATION COMPLETE\n";
echo "==============================================\n";

$allGood = $tables['agent_tiers'] && $tables['agent_pricing'] && $hasTierIdColumn;

if ($allGood) {
    echo "✅ All agent-related migrations are applied!\n";
    echo "✅ Database schema is ready for agent assignment.\n\n";

    if (DB::table('agent_tiers')->count() === 0) {
        echo "⚠️  Next step: Seed agent tiers data\n";
        echo "   Run: php artisan db:seed --class=AgentTiersSeeder\n\n";
    }
} else {
    echo "⚠️  Some migrations are missing or failed.\n";
    echo "   Please review the errors above.\n\n";
}

exit($allGood ? 0 : 1);

<?php

namespace App\Console\Commands;

use App\Services\QueryOptimizer;
use Illuminate\Console\Command;

/**
 * Analyze Database Queries Command
 *
 * Analyzes slow queries and provides optimization suggestions
 */
class AnalyzeQueriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:analyze-queries
                            {--threshold=1000 : Slow query threshold in milliseconds}
                            {--show-indexes : Show index usage statistics}
                            {--unused-indexes : Show unused indexes}
                            {--suggest-indexes= : Suggest indexes for a table}
                            {--table-stats= : Show statistics for a table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze database queries and provide optimization suggestions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Database Query Analyzer');
        $this->newLine();

        // Show index usage stats
        if ($this->option('show-indexes')) {
            return $this->showIndexStats();
        }

        // Show unused indexes
        if ($this->option('unused-indexes')) {
            return $this->showUnusedIndexes();
        }

        // Suggest indexes for a table
        if ($tableName = $this->option('suggest-indexes')) {
            return $this->suggestIndexes($tableName);
        }

        // Show table statistics
        if ($tableName = $this->option('table-stats')) {
            return $this->showTableStats($tableName);
        }

        // Default: show slow queries
        return $this->showSlowQueries();
    }

    /**
     * Show slow queries
     */
    private function showSlowQueries(): int
    {
        $threshold = (int) $this->option('threshold');

        $this->info("Slow Queries (threshold: {$threshold}ms)");
        $this->newLine();

        try {
            $queries = QueryOptimizer::getSlowQueries($threshold);

            if (empty($queries)) {
                $this->info('✓ No slow queries found!');
                return self::SUCCESS;
            }

            $this->warn("Found " . count($queries) . " slow queries");
            $this->newLine();

            $tableData = [];
            foreach ($queries as $query) {
                $tableData[] = [
                    'Query' => \Illuminate\Support\Str::limit($query['query'], 60),
                    'Calls' => $query['calls'],
                    'Avg Time (ms)' => round($query['mean_time'], 2),
                    'Max Time (ms)' => round($query['max_time'], 2),
                    'Total Time (s)' => round($query['total_time'] / 1000, 2),
                ];
            }

            $this->table(
                ['Query', 'Calls', 'Avg Time (ms)', 'Max Time (ms)', 'Total Time (s)'],
                $tableData
            );

            $this->newLine();
            $this->comment('Analyze specific queries:');
            $this->line('  php artisan tinker');
            $this->line('  QueryOptimizer::analyze("YOUR_QUERY_HERE")');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to analyze queries: ' . $e->getMessage());
            $this->newLine();
            $this->comment('Note: pg_stat_statements extension must be enabled in PostgreSQL');
            $this->comment('Enable it by adding to postgresql.conf:');
            $this->line('  shared_preload_libraries = \'pg_stat_statements\'');
            $this->line('  pg_stat_statements.track = all');
            $this->comment('Then restart PostgreSQL and run:');
            $this->line('  CREATE EXTENSION IF NOT EXISTS pg_stat_statements;');

            return self::FAILURE;
        }
    }

    /**
     * Show index usage statistics
     */
    private function showIndexStats(): int
    {
        $this->info('Index Usage Statistics');
        $this->newLine();

        try {
            $stats = QueryOptimizer::getIndexStats();

            if (empty($stats)) {
                $this->info('No index statistics available');
                return self::SUCCESS;
            }

            $tableData = [];
            foreach ($stats as $stat) {
                $tableData[] = [
                    'Table' => $stat['tablename'],
                    'Index' => $stat['indexname'],
                    'Scans' => $stat['idx_scan'],
                    'Tuples Read' => $stat['idx_tup_read'],
                    'Tuples Fetched' => $stat['idx_tup_fetch'],
                ];
            }

            $this->table(
                ['Table', 'Index', 'Scans', 'Tuples Read', 'Tuples Fetched'],
                $tableData
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to get index statistics: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Show unused indexes
     */
    private function showUnusedIndexes(): int
    {
        $this->info('Unused Indexes');
        $this->newLine();

        try {
            $indexes = QueryOptimizer::findUnusedIndexes();

            if (empty($indexes)) {
                $this->info('✓ All indexes are being used!');
                return self::SUCCESS;
            }

            $this->warn("Found " . count($indexes) . " unused indexes");
            $this->newLine();

            $tableData = [];
            foreach ($indexes as $index) {
                $tableData[] = [
                    'Schema' => $index['schemaname'],
                    'Table' => $index['tablename'],
                    'Index' => $index['indexname'],
                    'Size' => $index['index_size'],
                ];
            }

            $this->table(
                ['Schema', 'Table', 'Index', 'Size'],
                $tableData
            );

            $this->newLine();
            $this->comment('Consider dropping unused indexes to save space and improve write performance');
            $this->comment('Example: DROP INDEX index_name;');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to find unused indexes: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Suggest indexes for a table
     */
    private function suggestIndexes(string $tableName): int
    {
        $this->info("Index Suggestions for table: {$tableName}");
        $this->newLine();

        try {
            $suggestions = QueryOptimizer::suggestIndexes($tableName);

            if (empty($suggestions)) {
                $this->info('No index suggestions for this table');
                return self::SUCCESS;
            }

            $this->warn("Found " . count($suggestions) . " index suggestions");
            $this->newLine();

            foreach ($suggestions as $suggestion) {
                $this->line("CREATE INDEX idx_{$suggestion['table']}_{$suggestion['column']} ");
                $this->line("ON {$suggestion['table']} USING {$suggestion['type']} ({$suggestion['column']});");
                $this->comment("  Reason: {$suggestion['reason']}");
                $this->newLine();
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to suggest indexes: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Show table statistics
     */
    private function showTableStats(?string $tableName = null): int
    {
        $title = $tableName ? "Statistics for table: {$tableName}" : "Table Statistics (Top 20 by size)";
        $this->info($title);
        $this->newLine();

        try {
            $stats = QueryOptimizer::getTableStats($tableName);

            if (empty($stats)) {
                $this->info('No statistics available');
                return self::SUCCESS;
            }

            $tableData = [];
            foreach ($stats as $stat) {
                $tableData[] = [
                    'Table' => $stat['tablename'],
                    'Total Size' => $stat['total_size'],
                    'Table Size' => $stat['table_size'],
                    'Indexes Size' => $stat['indexes_size'],
                    'Live Rows' => number_format($stat['live_rows']),
                    'Dead Rows' => number_format($stat['dead_rows']),
                ];
            }

            $this->table(
                ['Table', 'Total Size', 'Table Size', 'Indexes Size', 'Live Rows', 'Dead Rows'],
                $tableData
            );

            // Show vacuum info
            if ($tableName && !empty($stats)) {
                $this->newLine();
                $stat = $stats[0];
                $this->info('Maintenance Info:');
                $this->line("  Last Vacuum: " . ($stat['last_vacuum'] ?? 'Never'));
                $this->line("  Last Auto-Vacuum: " . ($stat['last_autovacuum'] ?? 'Never'));
                $this->line("  Last Analyze: " . ($stat['last_analyze'] ?? 'Never'));
                $this->line("  Last Auto-Analyze: " . ($stat['last_autoanalyze'] ?? 'Never'));

                if ($stat['dead_rows'] > 0) {
                    $deadRatio = $stat['dead_rows'] / max($stat['live_rows'], 1) * 100;
                    if ($deadRatio > 10) {
                        $this->newLine();
                        $this->warn("Table has {$deadRatio}% dead rows - consider running VACUUM");
                        $this->comment("Run: VACUUM ANALYZE {$tableName};");
                    }
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to get table statistics: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

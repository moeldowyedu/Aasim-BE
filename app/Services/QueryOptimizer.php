<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Database Query Optimizer
 *
 * Analyzes queries, suggests optimizations, and monitors performance
 */
class QueryOptimizer
{
    private const CACHE_PREFIX = 'query_optimizer:';
    private const SLOW_QUERY_THRESHOLD = 1000; // 1 second in milliseconds

    /**
     * Analyze a query and provide optimization suggestions
     */
    public static function analyze(string $query, array $bindings = []): array
    {
        $startTime = microtime(true);

        // Get query explanation
        $explanation = self::explainQuery($query, $bindings);

        // Analyze the query
        $suggestions = [];

        // Check for SELECT *
        if (self::hasSelectAll($query)) {
            $suggestions[] = [
                'type' => 'select_all',
                'severity' => 'medium',
                'message' => 'Avoid using SELECT * - specify only needed columns',
                'impact' => 'Reduces memory usage and network transfer',
            ];
        }

        // Check for missing WHERE clause
        if (self::isMissingWhere($query)) {
            $suggestions[] = [
                'type' => 'missing_where',
                'severity' => 'high',
                'message' => 'Query has no WHERE clause - may scan entire table',
                'impact' => 'Poor performance on large tables',
            ];
        }

        // Check for N+1 query patterns
        if (self::isPotentialNPlusOne($query)) {
            $suggestions[] = [
                'type' => 'n_plus_one',
                'severity' => 'high',
                'message' => 'Potential N+1 query detected - consider eager loading',
                'impact' => 'Multiple queries instead of one JOIN',
            ];
        }

        // Check for inefficient LIKE patterns
        if (self::hasLeadingWildcard($query)) {
            $suggestions[] = [
                'type' => 'leading_wildcard',
                'severity' => 'high',
                'message' => 'LIKE pattern starts with wildcard - cannot use index',
                'impact' => 'Full table scan instead of index scan',
            ];
        }

        // Check for missing indexes
        $missingIndexes = self::findMissingIndexes($explanation);
        if (!empty($missingIndexes)) {
            $suggestions[] = [
                'type' => 'missing_index',
                'severity' => 'critical',
                'message' => 'Missing index detected on: ' . implode(', ', $missingIndexes),
                'impact' => 'Full table scan - add indexes to improve performance',
            ];
        }

        // Check for filesort
        if (self::hasFilesort($explanation)) {
            $suggestions[] = [
                'type' => 'filesort',
                'severity' => 'medium',
                'message' => 'Query uses filesort - consider adding composite index for ORDER BY columns',
                'impact' => 'Sorting in memory or temp files instead of using index',
            ];
        }

        // Check for temporary table
        if (self::usesTempTable($explanation)) {
            $suggestions[] = [
                'type' => 'temp_table',
                'severity' => 'medium',
                'message' => 'Query uses temporary table - optimize JOIN or GROUP BY',
                'impact' => 'Additional I/O operations',
            ];
        }

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'query' => $query,
            'execution_plan' => $explanation,
            'suggestions' => $suggestions,
            'analysis_time_ms' => round($duration, 2),
        ];
    }

    /**
     * Get query execution plan (EXPLAIN)
     */
    private static function explainQuery(string $query, array $bindings = []): array
    {
        try {
            // Replace bindings in query for EXPLAIN
            $explainQuery = self::bindValues($query, $bindings);

            // Run EXPLAIN
            $result = DB::select("EXPLAIN (FORMAT JSON) {$explainQuery}");

            if (empty($result)) {
                return [];
            }

            // PostgreSQL returns JSON in a column
            $json = $result[0]->{'QUERY PLAN'} ?? json_encode([]);

            return json_decode($json, true);
        } catch (\Exception $e) {
            Log::warning('Failed to explain query', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if query uses SELECT *
     */
    private static function hasSelectAll(string $query): bool
    {
        return preg_match('/SELECT\s+\*/i', $query) === 1;
    }

    /**
     * Check if query is missing WHERE clause
     */
    private static function isMissingWhere(string $query): bool
    {
        // Skip for certain safe queries
        if (preg_match('/LIMIT\s+1/i', $query)) {
            return false;
        }

        return preg_match('/WHERE/i', $query) === 0 &&
               preg_match('/DELETE|UPDATE/i', $query) === 0;
    }

    /**
     * Check for potential N+1 query pattern
     */
    private static function isPotentialNPlusOne(string $query): bool
    {
        // Simple heuristic: single row SELECT with IN clause
        return preg_match('/SELECT.*WHERE.*IN\s*\(/i', $query) === 1 &&
               preg_match('/LIMIT\s+1/i', $query) === 0;
    }

    /**
     * Check for leading wildcard in LIKE
     */
    private static function hasLeadingWildcard(string $query): bool
    {
        return preg_match('/LIKE\s+[\'"]%/i', $query) === 1 ||
               preg_match('/ILIKE\s+[\'"]%/i', $query) === 1;
    }

    /**
     * Find missing indexes from execution plan
     */
    private static function findMissingIndexes(array $explanation): array
    {
        $missingIndexes = [];

        // Analyze PostgreSQL EXPLAIN output
        if (isset($explanation['Plan'])) {
            $plan = $explanation['Plan'];

            // Check for Seq Scan (full table scan)
            if ($plan['Node Type'] === 'Seq Scan') {
                if (isset($plan['Filter'])) {
                    // Extract column names from filter
                    preg_match_all('/\((\w+)\s*[=<>]/', $plan['Filter'], $matches);
                    $missingIndexes = array_merge($missingIndexes, $matches[1] ?? []);
                }
            }

            // Recursive check for nested plans
            if (isset($plan['Plans'])) {
                foreach ($plan['Plans'] as $nestedPlan) {
                    $nested = self::findMissingIndexes(['Plan' => $nestedPlan]);
                    $missingIndexes = array_merge($missingIndexes, $nested);
                }
            }
        }

        return array_unique($missingIndexes);
    }

    /**
     * Check if execution plan uses filesort
     */
    private static function hasFilesort(array $explanation): bool
    {
        if (isset($explanation['Plan'])) {
            $plan = $explanation['Plan'];

            // Check for Sort node without index
            if ($plan['Node Type'] === 'Sort') {
                return true;
            }

            // Recursive check
            if (isset($plan['Plans'])) {
                foreach ($plan['Plans'] as $nestedPlan) {
                    if (self::hasFilesort(['Plan' => $nestedPlan])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if execution plan uses temporary table
     */
    private static function usesTempTable(array $explanation): bool
    {
        if (isset($explanation['Plan'])) {
            $plan = $explanation['Plan'];

            // Check for Hash or HashAggregate (uses temp space)
            if (in_array($plan['Node Type'], ['Hash', 'HashAggregate', 'Materialize'])) {
                return true;
            }

            // Recursive check
            if (isset($plan['Plans'])) {
                foreach ($plan['Plans'] as $nestedPlan) {
                    if (self::usesTempTable(['Plan' => $nestedPlan])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Bind values to query for EXPLAIN
     */
    private static function bindValues(string $query, array $bindings): string
    {
        if (empty($bindings)) {
            return $query;
        }

        // Replace ? placeholders with actual values
        $query = preg_replace_callback('/\?/', function () use (&$bindings) {
            $value = array_shift($bindings);

            if (is_null($value)) {
                return 'NULL';
            }

            if (is_numeric($value)) {
                return $value;
            }

            if (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            }

            return "'" . addslashes($value) . "'";
        }, $query);

        return $query;
    }

    /**
     * Get slow queries from log
     */
    public static function getSlowQueries(int $threshold = null): array
    {
        $threshold = $threshold ?? self::SLOW_QUERY_THRESHOLD;

        return Cache::remember(self::CACHE_PREFIX . 'slow_queries', 300, function () use ($threshold) {
            // Query PostgreSQL slow query log
            $queries = DB::select("
                SELECT
                    query,
                    calls,
                    total_time,
                    mean_time,
                    max_time,
                    min_time,
                    stddev_time
                FROM pg_stat_statements
                WHERE mean_time > ?
                ORDER BY mean_time DESC
                LIMIT 50
            ", [$threshold]);

            return array_map(fn($q) => (array) $q, $queries);
        });
    }

    /**
     * Get index usage statistics
     */
    public static function getIndexStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX . 'index_stats', 300, function () {
            $stats = DB::select("
                SELECT
                    schemaname,
                    tablename,
                    indexname,
                    idx_scan,
                    idx_tup_read,
                    idx_tup_fetch
                FROM pg_stat_user_indexes
                ORDER BY idx_scan ASC
                LIMIT 50
            ");

            return array_map(fn($s) => (array) $s, $stats);
        });
    }

    /**
     * Find unused indexes
     */
    public static function findUnusedIndexes(): array
    {
        return Cache::remember(self::CACHE_PREFIX . 'unused_indexes', 600, function () {
            $indexes = DB::select("
                SELECT
                    schemaname,
                    tablename,
                    indexname,
                    pg_size_pretty(pg_relation_size(indexrelid)) as index_size
                FROM pg_stat_user_indexes
                WHERE idx_scan = 0
                AND indexrelname NOT LIKE '%_pkey'
                ORDER BY pg_relation_size(indexrelid) DESC
            ");

            return array_map(fn($i) => (array) $i, $indexes);
        });
    }

    /**
     * Suggest indexes based on query patterns
     */
    public static function suggestIndexes(string $tableName): array
    {
        $suggestions = [];

        // Get columns used in WHERE clauses
        $whereColumns = DB::select("
            SELECT
                attname as column_name,
                n_distinct,
                correlation
            FROM pg_stats
            WHERE tablename = ?
            ORDER BY n_distinct DESC
        ", [$tableName]);

        foreach ($whereColumns as $col) {
            // High cardinality columns are good index candidates
            if ($col->n_distinct > 100 || $col->n_distinct < 0) {
                $suggestions[] = [
                    'table' => $tableName,
                    'column' => $col->column_name,
                    'type' => 'btree',
                    'reason' => 'High cardinality column',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get table statistics
     */
    public static function getTableStats(string $tableName = null): array
    {
        $query = "
            SELECT
                schemaname,
                tablename,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as total_size,
                pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) as table_size,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) as indexes_size,
                n_tup_ins as inserts,
                n_tup_upd as updates,
                n_tup_del as deletes,
                n_live_tup as live_rows,
                n_dead_tup as dead_rows,
                last_vacuum,
                last_autovacuum,
                last_analyze,
                last_autoanalyze
            FROM pg_stat_user_tables
        ";

        if ($tableName) {
            $query .= " WHERE tablename = ?";
            $stats = DB::select($query, [$tableName]);
        } else {
            $query .= " ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC LIMIT 20";
            $stats = DB::select($query);
        }

        return array_map(fn($s) => (array) $s, $stats);
    }

    /**
     * Monitor query performance
     */
    public static function recordQueryPerformance(string $query, float $duration, array $bindings = []): void
    {
        // Only record slow queries
        if ($duration < self::SLOW_QUERY_THRESHOLD) {
            return;
        }

        $key = self::CACHE_PREFIX . 'slow:' . md5($query);

        $data = Cache::get($key, [
            'query' => $query,
            'count' => 0,
            'total_time' => 0,
            'max_time' => 0,
            'min_time' => PHP_FLOAT_MAX,
        ]);

        $data['count']++;
        $data['total_time'] += $duration;
        $data['max_time'] = max($data['max_time'], $duration);
        $data['min_time'] = min($data['min_time'], $duration);
        $data['avg_time'] = $data['total_time'] / $data['count'];
        $data['last_seen'] = now()->toIso8601String();

        Cache::put($key, $data, 3600); // Store for 1 hour

        // Log critical slow queries
        if ($duration > 5000) { // > 5 seconds
            StructuredLogger::warning('Critical slow query detected', [
                'query' => $query,
                'duration_ms' => $duration,
            ], StructuredLogger::CATEGORY_DATABASE);
        }
    }
}

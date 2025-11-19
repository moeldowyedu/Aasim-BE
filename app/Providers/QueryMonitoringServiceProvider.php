<?php

namespace App\Providers;

use App\Services\QueryOptimizer;
use App\Services\StructuredLogger;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\QueryExecuted;

/**
 * Query Monitoring Service Provider
 *
 * Monitors database queries and logs slow queries automatically
 */
class QueryMonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Only monitor in specific environments
        if (!$this->shouldMonitor()) {
            return;
        }

        // Listen to all database queries
        DB::listen(function (QueryExecuted $query) {
            $this->monitorQuery($query);
        });

        // Log query count at end of request
        if (!app()->runningInConsole()) {
            register_shutdown_function(function () {
                $this->logQueryCount();
            });
        }
    }

    /**
     * Check if query monitoring should be enabled
     */
    private function shouldMonitor(): bool
    {
        $env = config('app.env');
        $enabled = config('database.query_monitoring.enabled', false);

        // Enable in development and staging by default
        return $enabled || in_array($env, ['local', 'development', 'staging']);
    }

    /**
     * Monitor individual query
     */
    private function monitorQuery(QueryExecuted $query): void
    {
        $time = $query->time; // Time in milliseconds

        // Always log slow queries
        if ($time >= config('database.query_monitoring.slow_threshold', 1000)) {
            $this->logSlowQuery($query, $time);
        }

        // Record query performance
        QueryOptimizer::recordQueryPerformance(
            $query->sql,
            $time,
            $query->bindings
        );

        // Log all queries in debug mode
        if (config('database.query_monitoring.log_all', false)) {
            StructuredLogger::databaseQuery(
                $query->sql,
                $time,
                null,
                $query->bindings
            );
        }
    }

    /**
     * Log slow query with details
     */
    private function logSlowQuery(QueryExecuted $query, float $time): void
    {
        // Get caller information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $caller = $this->findQueryCaller($backtrace);

        StructuredLogger::warning('Slow query detected', [
            'query' => $query->sql,
            'bindings' => $query->bindings,
            'time_ms' => $time,
            'connection' => $query->connectionName,
            'caller_file' => $caller['file'] ?? null,
            'caller_line' => $caller['line'] ?? null,
            'caller_class' => $caller['class'] ?? null,
            'caller_function' => $caller['function'] ?? null,
        ], StructuredLogger::CATEGORY_DATABASE);

        // Get optimization suggestions in development
        if (config('app.env') === 'local') {
            $analysis = QueryOptimizer::analyze($query->sql, $query->bindings);

            if (!empty($analysis['suggestions'])) {
                StructuredLogger::info('Query optimization suggestions', [
                    'query' => $query->sql,
                    'suggestions' => $analysis['suggestions'],
                ], StructuredLogger::CATEGORY_DATABASE);
            }
        }
    }

    /**
     * Find the code location that triggered the query
     */
    private function findQueryCaller(array $backtrace): array
    {
        foreach ($backtrace as $trace) {
            // Skip Laravel internal files
            if (isset($trace['file']) &&
                !str_contains($trace['file'], '/vendor/laravel/') &&
                !str_contains($trace['file'], '/vendor/illuminate/')
            ) {
                return [
                    'file' => $trace['file'] ?? null,
                    'line' => $trace['line'] ?? null,
                    'class' => $trace['class'] ?? null,
                    'function' => $trace['function'] ?? null,
                ];
            }
        }

        return [];
    }

    /**
     * Log total query count for the request
     */
    private function logQueryCount(): void
    {
        // Get query log if enabled
        if (!config('database.connections.' . config('database.default') . '.log_queries', false)) {
            return;
        }

        $queries = DB::getQueryLog();
        $totalQueries = count($queries);
        $totalTime = array_sum(array_column($queries, 'time'));

        // Warn if too many queries (potential N+1)
        if ($totalQueries > config('database.query_monitoring.max_queries', 50)) {
            StructuredLogger::warning('High query count detected', [
                'total_queries' => $totalQueries,
                'total_time_ms' => $totalTime,
                'avg_time_ms' => $totalQueries > 0 ? round($totalTime / $totalQueries, 2) : 0,
                'hint' => 'Potential N+1 query problem - use eager loading',
            ], StructuredLogger::CATEGORY_PERFORMANCE);
        }

        // Log performance metrics
        if (config('database.query_monitoring.log_totals', true)) {
            StructuredLogger::performance('database_queries', $totalQueries, 'count', [
                'total_time_ms' => $totalTime,
            ]);
        }
    }
}

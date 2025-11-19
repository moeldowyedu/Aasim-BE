<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Query Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database query monitoring, slow query detection, and
    | query optimization suggestions
    |
    */

    /**
     * Enable query monitoring
     *
     * When enabled, all database queries will be monitored for performance
     */
    'enabled' => env('DB_QUERY_MONITORING_ENABLED', env('APP_ENV') !== 'production'),

    /**
     * Slow query threshold (milliseconds)
     *
     * Queries taking longer than this threshold will be logged as slow
     */
    'slow_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 1000), // 1 second

    /**
     * Log all queries (debug mode)
     *
     * When enabled, ALL queries will be logged (very verbose)
     * Only recommended for local development
     */
    'log_all' => env('DB_LOG_ALL_QUERIES', false),

    /**
     * Log query totals
     *
     * Log total query count and time at the end of each request
     */
    'log_totals' => env('DB_LOG_QUERY_TOTALS', true),

    /**
     * Maximum queries per request
     *
     * Warn if a request executes more than this many queries
     * This helps detect N+1 query problems
     */
    'max_queries' => env('DB_MAX_QUERIES_PER_REQUEST', 50),

    /**
     * Query optimization
     */
    'optimization' => [
        // Automatically analyze slow queries
        'auto_analyze' => env('DB_AUTO_ANALYZE_SLOW_QUERIES', env('APP_ENV') === 'local'),

        // Suggest optimizations in logs
        'suggest_optimizations' => env('DB_SUGGEST_OPTIMIZATIONS', env('APP_ENV') === 'local'),

        // Cache optimization suggestions
        'cache_suggestions' => true,
        'cache_ttl' => 3600, // 1 hour
    ],

    /**
     * PostgreSQL specific settings
     */
    'postgresql' => [
        // Enable pg_stat_statements for query statistics
        'pg_stat_statements' => env('DB_PG_STAT_STATEMENTS_ENABLED', true),

        // Track query planning time (PostgreSQL 13+)
        'track_planning' => env('DB_TRACK_PLANNING', true),

        // Auto-explain threshold (ms)
        'auto_explain_threshold' => env('DB_AUTO_EXPLAIN_THRESHOLD', 5000), // 5 seconds
    ],

    /**
     * Index analysis
     */
    'indexes' => [
        // Analyze index usage
        'analyze_usage' => true,

        // Find unused indexes
        'find_unused' => true,

        // Suggest missing indexes
        'suggest_missing' => true,

        // Minimum index scans to consider "used"
        'min_scans_for_used' => 100,
    ],

    /**
     * Query patterns to watch for
     */
    'patterns' => [
        // Detect SELECT * queries
        'select_all' => true,

        // Detect missing WHERE clauses
        'missing_where' => true,

        // Detect leading wildcards in LIKE
        'leading_wildcard' => true,

        // Detect potential N+1 queries
        'n_plus_one' => true,

        // Detect inefficient OR conditions
        'inefficient_or' => true,

        // Detect missing indexes
        'missing_indexes' => true,
    ],

    /**
     * Alerting
     */
    'alerts' => [
        // Send alerts for critical slow queries
        'enabled' => env('DB_ALERTS_ENABLED', false),

        // Threshold for critical alerts (milliseconds)
        'critical_threshold' => env('DB_CRITICAL_QUERY_THRESHOLD', 10000), // 10 seconds

        // Alert channels (log, slack, email, etc.)
        'channels' => ['log'],

        // Slack webhook URL
        'slack_webhook' => env('DB_ALERTS_SLACK_WEBHOOK'),

        // Email recipients
        'email_recipients' => env('DB_ALERTS_EMAIL', ''),
    ],

    /**
     * Query log retention
     */
    'retention' => [
        // Keep slow query logs for this many days
        'slow_queries_days' => 7,

        // Keep performance metrics for this many days
        'metrics_days' => 30,

        // Cleanup schedule (cron expression)
        'cleanup_schedule' => '0 2 * * *', // 2 AM daily
    ],

    /**
     * Performance baselines
     */
    'baselines' => [
        // Define performance baselines for different query types

        // Simple SELECT queries
        'select' => [
            'target' => 10, // ms
            'acceptable' => 50, // ms
            'slow' => 100, // ms
        ],

        // INSERT queries
        'insert' => [
            'target' => 20, // ms
            'acceptable' => 100, // ms
            'slow' => 500, // ms
        ],

        // UPDATE queries
        'update' => [
            'target' => 20, // ms
            'acceptable' => 100, // ms
            'slow' => 500, // ms
        ],

        // Complex queries (JOINs, aggregations)
        'complex' => [
            'target' => 100, // ms
            'acceptable' => 500, // ms
            'slow' => 1000, // ms
        ],
    ],

    /**
     * Reporting
     */
    'reporting' => [
        // Generate daily query performance reports
        'daily_report' => env('DB_DAILY_REPORT', false),

        // Report recipients
        'report_recipients' => env('DB_REPORT_EMAIL', ''),

        // Include in reports
        'include' => [
            'slow_queries' => true,
            'query_count' => true,
            'index_usage' => true,
            'table_stats' => true,
            'optimization_suggestions' => true,
        ],
    ],
];

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Distributed Tracing Service using OpenTelemetry concepts
 *
 * This service provides distributed tracing capabilities to track
 * requests across multiple services and identify performance bottlenecks.
 */
class TracingService
{
    private static ?string $traceId = null;
    private static ?string $spanId = null;
    private static array $spans = [];

    /**
     * Initialize a new trace
     */
    public static function startTrace(string $operationName, array $context = []): string
    {
        self::$traceId = self::generateTraceId();
        self::$spanId = self::generateSpanId();

        $span = [
            'trace_id' => self::$traceId,
            'span_id' => self::$spanId,
            'parent_span_id' => null,
            'operation_name' => $operationName,
            'start_time' => microtime(true),
            'context' => $context,
            'tags' => [],
            'logs' => [],
        ];

        self::$spans[self::$spanId] = $span;

        Log::info('Trace started', [
            'trace_id' => self::$traceId,
            'span_id' => self::$spanId,
            'operation' => $operationName,
        ]);

        return self::$traceId;
    }

    /**
     * Start a new span within the current trace
     */
    public static function startSpan(string $operationName, ?string $parentSpanId = null, array $context = []): string
    {
        if (!self::$traceId) {
            self::startTrace($operationName, $context);
            return self::$spanId;
        }

        $spanId = self::generateSpanId();

        $span = [
            'trace_id' => self::$traceId,
            'span_id' => $spanId,
            'parent_span_id' => $parentSpanId ?? self::$spanId,
            'operation_name' => $operationName,
            'start_time' => microtime(true),
            'context' => $context,
            'tags' => [],
            'logs' => [],
        ];

        self::$spans[$spanId] = $span;

        return $spanId;
    }

    /**
     * End a span
     */
    public static function endSpan(string $spanId, array $tags = [], ?string $error = null): void
    {
        if (!isset(self::$spans[$spanId])) {
            return;
        }

        $span = &self::$spans[$spanId];
        $span['end_time'] = microtime(true);
        $span['duration_ms'] = ($span['end_time'] - $span['start_time']) * 1000;
        $span['tags'] = array_merge($span['tags'], $tags);

        if ($error) {
            $span['error'] = true;
            $span['error_message'] = $error;
        }

        // Log span completion
        Log::info('Span completed', [
            'trace_id' => $span['trace_id'],
            'span_id' => $spanId,
            'operation' => $span['operation_name'],
            'duration_ms' => round($span['duration_ms'], 2),
            'error' => $error !== null,
        ]);

        // Export to tracing backend (TODO: implement actual export)
        self::exportSpan($span);
    }

    /**
     * Add tags to current span
     */
    public static function addTags(string $spanId, array $tags): void
    {
        if (isset(self::$spans[$spanId])) {
            self::$spans[$spanId]['tags'] = array_merge(
                self::$spans[$spanId]['tags'] ?? [],
                $tags
            );
        }
    }

    /**
     * Add log to current span
     */
    public static function addLog(string $spanId, string $message, array $context = []): void
    {
        if (isset(self::$spans[$spanId])) {
            self::$spans[$spanId]['logs'][] = [
                'timestamp' => microtime(true),
                'message' => $message,
                'context' => $context,
            ];
        }
    }

    /**
     * Get current trace ID
     */
    public static function getTraceId(): ?string
    {
        return self::$traceId;
    }

    /**
     * Get current span ID
     */
    public static function getSpanId(): ?string
    {
        return self::$spanId;
    }

    /**
     * Get all spans for current trace
     */
    public static function getSpans(): array
    {
        return self::$spans;
    }

    /**
     * Export span to tracing backend
     */
    private static function exportSpan(array $span): void
    {
        // TODO: Export to Jaeger, Zipkin, or other tracing backend
        // For now, store in cache for metrics endpoint
        $key = "traces:{$span['trace_id']}";
        $traces = json_decode(cache()->get($key, '[]'), true);
        $traces[] = $span;
        cache()->put($key, json_encode($traces), 3600); // 1 hour
    }

    /**
     * Generate trace ID (128-bit)
     */
    private static function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate span ID (64-bit)
     */
    private static function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Clear current trace
     */
    public static function clearTrace(): void
    {
        self::$traceId = null;
        self::$spanId = null;
        self::$spans = [];
    }

    /**
     * Get trace context for propagation
     */
    public static function getTraceContext(): array
    {
        return [
            'traceparent' => sprintf(
                '00-%s-%s-01',
                self::$traceId ?? '00000000000000000000000000000000',
                self::$spanId ?? '0000000000000000'
            ),
            'tracestate' => 'aasim=1',
        ];
    }

    /**
     * Extract trace context from headers
     */
    public static function extractTraceContext(array $headers): void
    {
        $traceparent = $headers['traceparent'] ?? null;

        if ($traceparent && preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-/', $traceparent, $matches)) {
            self::$traceId = $matches[1];
            self::$spanId = $matches[2];
        }
    }
}

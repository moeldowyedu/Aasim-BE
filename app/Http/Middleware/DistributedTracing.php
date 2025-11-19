<?php

namespace App\Http\Middleware;

use App\Services\TracingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DistributedTracing
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract trace context from headers (for cross-service tracing)
        TracingService::extractTraceContext($request->headers->all());

        // Start trace
        $traceId = TracingService::startTrace(
            operationName: $request->method() . ' ' . $request->path(),
            context: [
                'http.method' => $request->method(),
                'http.url' => $request->fullUrl(),
                'http.route' => $request->route()?->getName(),
                'user_id' => $request->user()?->id,
                'tenant_id' => tenant('id'),
                'client_ip' => $request->ip(),
            ]
        );

        $spanId = TracingService::getSpanId();

        // Add trace headers to request for downstream services
        $traceContext = TracingService::getTraceContext();
        $request->headers->set('X-Trace-ID', $traceId);
        $request->headers->set('X-Span-ID', $spanId);

        try {
            // Process request
            $response = $next($request);

            // Add tags for successful request
            TracingService::addTags($spanId, [
                'http.status_code' => $response->getStatusCode(),
                'http.response_size' => strlen($response->getContent()),
                'success' => true,
            ]);

            // End span
            TracingService::endSpan($spanId, [
                'http.status' => $response->getStatusCode(),
            ]);

            // Add trace headers to response
            $response->headers->set('X-Trace-ID', $traceId);
            $response->headers->set('X-Span-ID', $spanId);

            return $response;

        } catch (\Throwable $e) {
            // Add error tags
            TracingService::addTags($spanId, [
                'error' => true,
                'error.type' => get_class($e),
                'error.message' => $e->getMessage(),
            ]);

            // End span with error
            TracingService::endSpan($spanId, [], $e->getMessage());

            throw $e;
        } finally {
            // Clear trace after request
            TracingService::clearTrace();
        }
    }
}

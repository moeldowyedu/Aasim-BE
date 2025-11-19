<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CDN Service for managing static assets across different CDN providers
 *
 * Supports:
 * - CloudFront (AWS)
 * - Cloudflare
 * - Fastly
 * - Bunny CDN
 * - Local fallback
 */
class CDNService
{
    private const CACHE_PREFIX = 'cdn:';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get CDN URL for an asset
     */
    public static function asset(string $path, ?string $version = null): string
    {
        if (!config('cdn.enabled')) {
            return self::localAsset($path);
        }

        $provider = config('cdn.provider');
        $baseUrl = self::getBaseUrl($provider);

        // Add version for cache busting
        $version = $version ?? self::getAssetVersion();
        $path = ltrim($path, '/');

        // Build URL with version
        if ($version) {
            $separator = str_contains($path, '?') ? '&' : '?';
            $path .= "{$separator}v={$version}";
        }

        return rtrim($baseUrl, '/') . '/' . $path;
    }

    /**
     * Get CDN URL for uploaded file
     */
    public static function url(string $path, bool $signed = false, int $ttl = 3600): string
    {
        if (!config('cdn.enabled')) {
            return self::localAsset($path);
        }

        $provider = config('cdn.provider');
        $baseUrl = self::getBaseUrl($provider);
        $path = ltrim($path, '/');

        $url = rtrim($baseUrl, '/') . '/' . $path;

        // Generate signed URL if requested
        if ($signed) {
            $url = self::generateSignedUrl($url, $ttl, $provider);
        }

        return $url;
    }

    /**
     * Purge cache for specific paths
     */
    public static function purge(array $paths): bool
    {
        $provider = config('cdn.provider');

        try {
            return match ($provider) {
                'cloudfront' => self::purgeCloudFront($paths),
                'cloudflare' => self::purgeCloudflare($paths),
                'fastly' => self::purgeFastly($paths),
                'bunny' => self::purgeBunny($paths),
                default => true,
            };
        } catch (\Exception $e) {
            Log::error('CDN purge failed', [
                'provider' => $provider,
                'paths' => $paths,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Purge all CDN cache
     */
    public static function purgeAll(): bool
    {
        $provider = config('cdn.provider');

        try {
            return match ($provider) {
                'cloudfront' => self::purgeCloudFront(['/*']),
                'cloudflare' => self::purgeCloudflareAll(),
                'fastly' => self::purgeFastlyAll(),
                'bunny' => self::purgeBunnyAll(),
                default => true,
            };
        } catch (\Exception $e) {
            Log::error('CDN purge all failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get asset version for cache busting
     */
    private static function getAssetVersion(): string
    {
        return Cache::remember(self::CACHE_PREFIX . 'version', self::CACHE_TTL, function () {
            // Use git commit hash if available
            if (file_exists(base_path('.git/HEAD'))) {
                $head = file_get_contents(base_path('.git/HEAD'));
                if (preg_match('/ref: (.+)/', $head, $matches)) {
                    $refPath = base_path('.git/' . trim($matches[1]));
                    if (file_exists($refPath)) {
                        return substr(file_get_contents($refPath), 0, 8);
                    }
                }
            }

            // Fallback to app version
            return config('app.version', '1.0.0');
        });
    }

    /**
     * Get base URL for CDN provider
     */
    private static function getBaseUrl(string $provider): string
    {
        return config("cdn.providers.{$provider}.url", config('app.url'));
    }

    /**
     * Get local asset URL
     */
    private static function localAsset(string $path): string
    {
        return asset($path);
    }

    /**
     * Generate signed URL for secure access
     */
    private static function generateSignedUrl(string $url, int $ttl, string $provider): string
    {
        return match ($provider) {
            'cloudfront' => self::signCloudFront($url, $ttl),
            'cloudflare' => self::signCloudflare($url, $ttl),
            'bunny' => self::signBunny($url, $ttl),
            default => $url,
        };
    }

    /**
     * CloudFront signed URL
     */
    private static function signCloudFront(string $url, int $ttl): string
    {
        $keyPairId = config('cdn.providers.cloudfront.key_pair_id');
        $privateKey = config('cdn.providers.cloudfront.private_key_path');
        $expires = time() + $ttl;

        if (!$keyPairId || !$privateKey || !file_exists($privateKey)) {
            return $url;
        }

        $policy = json_encode([
            'Statement' => [
                [
                    'Resource' => $url,
                    'Condition' => [
                        'DateLessThan' => ['AWS:EpochTime' => $expires],
                    ],
                ],
            ],
        ]);

        $signature = '';
        openssl_sign($policy, $signature, file_get_contents($privateKey), OPENSSL_ALGO_SHA1);
        $signature = self::base64UrlEncode($signature);
        $policy = self::base64UrlEncode($policy);

        $separator = str_contains($url, '?') ? '&' : '?';
        return "{$url}{$separator}Expires={$expires}&Signature={$signature}&Key-Pair-Id={$keyPairId}";
    }

    /**
     * Cloudflare signed URL (using signed tokens)
     */
    private static function signCloudflare(string $url, int $ttl): string
    {
        $secret = config('cdn.providers.cloudflare.token_secret');
        if (!$secret) {
            return $url;
        }

        $expires = time() + $ttl;
        $path = parse_url($url, PHP_URL_PATH);

        $message = $path . $expires;
        $signature = hash_hmac('sha256', $message, $secret);

        $separator = str_contains($url, '?') ? '&' : '?';
        return "{$url}{$separator}token={$signature}&expires={$expires}";
    }

    /**
     * Bunny CDN signed URL
     */
    private static function signBunny(string $url, int $ttl): string
    {
        $secret = config('cdn.providers.bunny.token_secret');
        if (!$secret) {
            return $url;
        }

        $expires = time() + $ttl;
        $path = parse_url($url, PHP_URL_PATH);

        $hash = hash('sha256', $secret . $path . $expires);
        $token = base64_encode($hash);

        $separator = str_contains($url, '?') ? '&' : '?';
        return "{$url}{$separator}token={$token}&expires={$expires}";
    }

    /**
     * Purge CloudFront cache
     */
    private static function purgeCloudFront(array $paths): bool
    {
        $distributionId = config('cdn.providers.cloudfront.distribution_id');

        if (!$distributionId) {
            return false;
        }

        // Note: This requires AWS SDK
        // aws cloudfront create-invalidation --distribution-id {$distributionId} --paths {$paths}

        StructuredLogger::info('CloudFront cache purge initiated', [
            'distribution_id' => $distributionId,
            'paths' => $paths,
        ], StructuredLogger::CATEGORY_INTEGRATION);

        return true;
    }

    /**
     * Purge Cloudflare cache
     */
    private static function purgeCloudflare(array $paths): bool
    {
        $zoneId = config('cdn.providers.cloudflare.zone_id');
        $apiToken = config('cdn.providers.cloudflare.api_token');

        if (!$zoneId || !$apiToken) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiToken}",
            'Content-Type' => 'application/json',
        ])->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
            'files' => array_map(fn($path) => config('cdn.providers.cloudflare.url') . '/' . ltrim($path, '/'), $paths),
        ]);

        return $response->successful();
    }

    /**
     * Purge all Cloudflare cache
     */
    private static function purgeCloudflareAll(): bool
    {
        $zoneId = config('cdn.providers.cloudflare.zone_id');
        $apiToken = config('cdn.providers.cloudflare.api_token');

        if (!$zoneId || !$apiToken) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiToken}",
            'Content-Type' => 'application/json',
        ])->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
            'purge_everything' => true,
        ]);

        return $response->successful();
    }

    /**
     * Purge Fastly cache
     */
    private static function purgeFastly(array $paths): bool
    {
        $apiKey = config('cdn.providers.fastly.api_key');
        $serviceId = config('cdn.providers.fastly.service_id');

        if (!$apiKey || !$serviceId) {
            return false;
        }

        foreach ($paths as $path) {
            Http::withHeaders([
                'Fastly-Key' => $apiKey,
            ])->post("https://api.fastly.com/service/{$serviceId}/purge/{$path}");
        }

        return true;
    }

    /**
     * Purge all Fastly cache
     */
    private static function purgeFastlyAll(): bool
    {
        $apiKey = config('cdn.providers.fastly.api_key');
        $serviceId = config('cdn.providers.fastly.service_id');

        if (!$apiKey || !$serviceId) {
            return false;
        }

        $response = Http::withHeaders([
            'Fastly-Key' => $apiKey,
        ])->post("https://api.fastly.com/service/{$serviceId}/purge_all");

        return $response->successful();
    }

    /**
     * Purge Bunny CDN cache
     */
    private static function purgeBunny(array $paths): bool
    {
        $apiKey = config('cdn.providers.bunny.api_key');
        $pullZoneId = config('cdn.providers.bunny.pull_zone_id');

        if (!$apiKey || !$pullZoneId) {
            return false;
        }

        foreach ($paths as $path) {
            Http::withHeaders([
                'AccessKey' => $apiKey,
            ])->post("https://api.bunny.net/pullzone/{$pullZoneId}/purgeCache", [
                'url' => config('cdn.providers.bunny.url') . '/' . ltrim($path, '/'),
            ]);
        }

        return true;
    }

    /**
     * Purge all Bunny CDN cache
     */
    private static function purgeBunnyAll(): bool
    {
        $apiKey = config('cdn.providers.bunny.api_key');
        $pullZoneId = config('cdn.providers.bunny.pull_zone_id');

        if (!$apiKey || !$pullZoneId) {
            return false;
        }

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
        ])->post("https://api.bunny.net/pullzone/{$pullZoneId}/purgeCache");

        return $response->successful();
    }

    /**
     * Base64 URL encode (for CloudFront)
     */
    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '=', '/'], ['-', '_', '~'], base64_encode($data));
    }

    /**
     * Get CDN statistics
     */
    public static function getStats(): array
    {
        $provider = config('cdn.provider');

        return [
            'provider' => $provider,
            'enabled' => config('cdn.enabled'),
            'base_url' => self::getBaseUrl($provider),
            'version' => self::getAssetVersion(),
        ];
    }

    /**
     * Preload assets (HTTP/2 push)
     */
    public static function preloadAssets(array $assets): string
    {
        $links = [];

        foreach ($assets as $asset) {
            $url = self::asset($asset['path']);
            $type = $asset['type'] ?? 'script';
            $links[] = "<{$url}>; rel=preload; as={$type}";
        }

        return implode(', ', $links);
    }

    /**
     * Warm cache by prefetching assets
     */
    public static function warmCache(array $urls): void
    {
        foreach ($urls as $url) {
            try {
                Http::timeout(5)->get($url);
                StructuredLogger::debug('CDN cache warmed', ['url' => $url], StructuredLogger::CATEGORY_CACHE);
            } catch (\Exception $e) {
                StructuredLogger::warning('CDN cache warm failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ], StructuredLogger::CATEGORY_CACHE);
            }
        }
    }
}

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'v1/*',
    ],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | This supports both static origins from .env and dynamic wildcard patterns
    | for tenant subdomains in multi-tenant architecture.
    |
    */
    'allowed_origins' => function () {
        // Get static origins from .env
        $envOrigins = env('CORS_ALLOWED_ORIGINS', '');
        $staticOrigins = $envOrigins ? explode(',', $envOrigins) : [];

        // Clean up any whitespace
        $staticOrigins = array_map('trim', $staticOrigins);

        // In production, we need to handle tenant subdomains dynamically
        // We'll return static origins here and use patterns below
        return array_filter($staticOrigins);
    },

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | These regex patterns allow wildcard subdomain support for multi-tenancy.
    | This enables any tenant subdomain to access the API while still
    | maintaining security through proper origin validation.
    |
    */
    'allowed_origins_patterns' => [
        // Production: Allow any subdomain under obsolio.com (HTTPS only)
        '/^https:\/\/([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)?obsolio\.com$/',

        // Development: Allow localhost with any subdomain and any port
        '/^http:\/\/([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)?localhost(:\d+)?$/',

        // Development: Allow 127.0.0.1 with any subdomain and any port
        '/^http:\/\/([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)?127\.0\.0\.1(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Authorization',
        'X-Tenant-Id',
    ],

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | This must be true for Sanctum/JWT authentication to work properly
    | across subdomains. It allows cookies and authorization headers
    | to be sent with cross-origin requests.
    |
    */
    'supports_credentials' => true,
];

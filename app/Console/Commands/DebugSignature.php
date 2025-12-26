<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class DebugSignature extends Command
{
    protected $signature = 'debug:signature';
    protected $description = 'Debug signed URL generation and validation';

    public function handle()
    {
        $userId = 1;
        $hash = sha1('test@example.com');
        $expires = Carbon::now()->addMinutes(60);

        $this->info("APP_URL from config: " . config('app.url'));
        $this->info("APP_KEY set: " . (config('app.key') ? 'Yes' : 'No'));

        // 1. Generate Standard Signed URL
        // Note: verify-email route is defined as: verify-email/{id}/{hash}
        // It does NOT have a name 'verification.verify' in the route group prefix 'v1'. 
        // Wait, looking at routes/api.php:
        // Route::prefix('v1')->group(function () { ... Route::get('verify-email/{id}/{hash}')->name('verification.verify'); ... });
        // So the name IS 'verification.verify'. But due to prefix, is it 'v1.verification.verify'? 
        // Usually names are not prefixed unless name() calls are grouped. Here it is direct.
        // But the URL will include /api/v1/ because of RouteServiceProvider or api.php mapping?
        // Let's assume the name is 'verification.verify'.

        try {
            $originalUrl = URL::temporarySignedRoute(
                'verification.verify',
                $expires,
                ['id' => $userId, 'hash' => $hash],
                false // Absolute
            );
        } catch (\Exception $e) {
            $this->error("Failed to generate route: " . $e->getMessage());
            // It might be because the route name is interpreted differently.
            // Let's try to check route name list if it fails.
            return;
        }

        $this->info("Original Generated URL:");
        $this->line($originalUrl);

        // 2. Simulate VerifyEmailNotification Logic
        $parsedUrl = parse_url($originalUrl);
        $forcedUrl = 'https://api.obsolio.com' . ($parsedUrl['path'] ?? '/') . '?' . ($parsedUrl['query'] ?? '');
        $this->info("\nForced API URL (sent to user):");
        $this->line($forcedUrl);

        // Extract signature
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $signature = $queryParams['signature'] ?? null;
        $expiresParam = $queryParams['expires'] ?? null;
        $this->info("\nSignature: " . $signature);
        $this->info("Expires: " . $expiresParam);

        // 3. Simulate VerificationController Logic
        $this->info("\n--- Validation Check ---");

        $checkUrls = [
            'https://obsolio.com',
            'https://api.obsolio.com'
        ];

        foreach ($checkUrls as $baseUrl) {
            // Reconstruct URL as built in controller
            // The controller uses $request->path(). 
            // For a request to https://api.obsolio.com/api/v1/verify-email/1/abc
            // $request->path() returns 'api/v1/verify-email/1/abc'

            $path = trim($parsedUrl['path'] ?? '/', '/');

            unset($queryParams['signature']);

            // Controller uses http_build_query($query)
            $queryPart = http_build_query($queryParams);

            $testUrl = $baseUrl . '/' . $path;
            if ($queryPart) {
                $testUrl .= '?' . $queryPart;
            }

            $computedHash = hash_hmac('sha256', $testUrl, config('app.key'));

            $match = hash_equals($computedHash, (string) $signature) ? 'MATCH' : 'FAIL';

            $this->info("Checking against base: $baseUrl");
            $this->line("constructed: $testUrl");
            $this->line("computed:    $computedHash");
            $this->line("result:      $match");
        }
    }
}

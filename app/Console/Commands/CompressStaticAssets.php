<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Compress Static Assets Command
 *
 * Pre-compresses static assets (CSS, JS, etc.) to .gz and .br files
 * for faster serving by web servers
 */
class CompressStaticAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assets:compress
                            {--force : Force recompression of already compressed files}
                            {--gzip-only : Only generate gzip files}
                            {--brotli-only : Only generate brotli files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-compress static assets for faster delivery';

    private int $filesProcessed = 0;
    private int $gzipGenerated = 0;
    private int $brotliGenerated = 0;
    private int $totalSaved = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('compression.static_assets.precompress')) {
            $this->error('Static asset precompression is disabled in config.');
            return self::FAILURE;
        }

        $this->info('Compressing static assets...');
        $this->newLine();

        $directories = config('compression.static_assets.directories', []);

        if (empty($directories)) {
            $this->warn('No directories configured for compression.');
            return self::SUCCESS;
        }

        $force = $this->option('force');
        $gzipOnly = $this->option('gzip-only');
        $brotliOnly = $this->option('brotli-only');

        foreach ($directories as $directory) {
            if (!File::isDirectory(base_path($directory))) {
                $this->warn("Directory not found: {$directory}");
                continue;
            }

            $this->info("Processing directory: {$directory}");
            $this->compressDirectory(base_path($directory), $force, $gzipOnly, $brotliOnly);
        }

        $this->newLine();
        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Compress all files in a directory
     */
    private function compressDirectory(string $path, bool $force, bool $gzipOnly, bool $brotliOnly): void
    {
        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($this->shouldCompress($file->getPathname())) {
                $this->compressFile($file->getPathname(), $force, $gzipOnly, $brotliOnly);
            }
        }
    }

    /**
     * Check if file should be compressed
     */
    private function shouldCompress(string $filePath): bool
    {
        // Skip already compressed files
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['gz', 'br', 'zip', '7z', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mp3'])) {
            return false;
        }

        // Only compress compressible types
        $compressible = ['css', 'js', 'mjs', 'html', 'htm', 'xml', 'json', 'svg', 'txt', 'woff', 'woff2', 'ttf', 'otf', 'eot'];

        return in_array($extension, $compressible);
    }

    /**
     * Compress a single file
     */
    private function compressFile(string $filePath, bool $force, bool $gzipOnly, bool $brotliOnly): void
    {
        $this->filesProcessed++;

        $content = file_get_contents($filePath);
        $originalSize = strlen($content);

        // Skip small files (< 1KB)
        if ($originalSize < 1024) {
            return;
        }

        $relativePath = str_replace(base_path() . '/', '', $filePath);

        // Generate Gzip file
        if (!$brotliOnly && function_exists('gzencode')) {
            $gzipPath = $filePath . '.gz';

            if ($force || !file_exists($gzipPath)) {
                $compressed = gzencode($content, config('compression.gzip.level', 9));

                if ($compressed && strlen($compressed) < $originalSize) {
                    file_put_contents($gzipPath, $compressed);
                    $this->gzipGenerated++;
                    $saved = $originalSize - strlen($compressed);
                    $this->totalSaved += $saved;

                    $ratio = round((1 - strlen($compressed) / $originalSize) * 100, 1);
                    $this->line("  <fg=green>✓</> {$relativePath}.gz (saved {$ratio}%)");
                }
            }
        }

        // Generate Brotli file
        if (!$gzipOnly && extension_loaded('brotli')) {
            $brotliPath = $filePath . '.br';

            if ($force || !file_exists($brotliPath)) {
                $compressed = brotli_compress($content, 11, BROTLI_TEXT); // Max compression for static files

                if ($compressed && strlen($compressed) < $originalSize) {
                    file_put_contents($brotliPath, $compressed);
                    $this->brotliGenerated++;
                    $saved = $originalSize - strlen($compressed);
                    $this->totalSaved += $saved;

                    $ratio = round((1 - strlen($compressed) / $originalSize) * 100, 1);
                    $this->line("  <fg=cyan>✓</> {$relativePath}.br (saved {$ratio}%)");
                }
            }
        } elseif (!$gzipOnly && !extension_loaded('brotli')) {
            // Only show this warning once
            static $brotliWarningShown = false;
            if (!$brotliWarningShown) {
                $this->warn('Brotli extension not installed. Install it for better compression.');
                $this->info('  Install: pecl install brotli');
                $brotliWarningShown = true;
            }
        }
    }

    /**
     * Display compression summary
     */
    private function displaySummary(): void
    {
        $this->info('Compression Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Files Processed', $this->filesProcessed],
                ['Gzip Files Generated', $this->gzipGenerated],
                ['Brotli Files Generated', $this->brotliGenerated],
                ['Total Space Saved', $this->formatBytes($this->totalSaved)],
            ]
        );

        $this->newLine();
        $this->info('✓ Static asset compression complete');

        if ($this->gzipGenerated > 0 || $this->brotliGenerated > 0) {
            $this->newLine();
            $this->comment('Configure your web server to serve pre-compressed files:');
            $this->newLine();
            $this->line('Nginx:');
            $this->line('  gzip_static on;');
            $this->line('  brotli_static on;');
            $this->newLine();
            $this->line('Apache (with mod_rewrite):');
            $this->line('  RewriteCond %{HTTP:Accept-Encoding} br');
            $this->line('  RewriteCond %{REQUEST_FILENAME}.br -f');
            $this->line('  RewriteRule ^(.*)$ $1.br [L]');
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

<?php

namespace App\Console\Commands;

use App\Services\CDNService;
use Illuminate\Console\Command;

/**
 * CDN Purge Command
 *
 * Purge CDN cache for specific paths or all assets
 */
class CDNPurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdn:purge
                            {paths?* : Specific paths to purge (optional)}
                            {--all : Purge all cached assets}
                            {--css : Purge CSS files}
                            {--js : Purge JavaScript files}
                            {--images : Purge image files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge CDN cache for specified paths or all assets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('cdn.enabled')) {
            $this->error('CDN is not enabled. Check your CDN_ENABLED environment variable.');
            return self::FAILURE;
        }

        $this->info('CDN Provider: ' . config('cdn.provider'));
        $this->newLine();

        if ($this->option('all')) {
            return $this->purgeAll();
        }

        $paths = $this->getPaths();

        if (empty($paths)) {
            $this->error('No paths specified. Use --all to purge everything or specify paths.');
            return self::FAILURE;
        }

        return $this->purgePaths($paths);
    }

    /**
     * Purge all cached assets
     */
    private function purgeAll(): int
    {
        $this->warn('This will purge ALL cached assets from the CDN.');

        if (!$this->confirm('Are you sure you want to continue?')) {
            $this->info('Purge cancelled.');
            return self::SUCCESS;
        }

        $this->info('Purging all assets...');

        $success = CDNService::purgeAll();

        if ($success) {
            $this->info('✓ All CDN cache purged successfully');
            return self::SUCCESS;
        }

        $this->error('✗ Failed to purge CDN cache. Check logs for details.');
        return self::FAILURE;
    }

    /**
     * Purge specific paths
     */
    private function purgePaths(array $paths): int
    {
        $this->info('Purging ' . count($paths) . ' path(s)...');
        $this->newLine();

        foreach ($paths as $path) {
            $this->line("  - {$path}");
        }

        $this->newLine();

        $success = CDNService::purge($paths);

        if ($success) {
            $this->info('✓ CDN cache purged successfully');
            $this->newLine();
            $this->info('Note: It may take a few minutes for changes to propagate globally.');
            return self::SUCCESS;
        }

        $this->error('✗ Failed to purge CDN cache. Check logs for details.');
        return self::FAILURE;
    }

    /**
     * Get paths to purge based on arguments and options
     */
    private function getPaths(): array
    {
        $paths = $this->argument('paths') ?? [];

        // Add paths based on options
        if ($this->option('css')) {
            $paths[] = '/css/*';
            $paths[] = '/build/assets/*.css';
        }

        if ($this->option('js')) {
            $paths[] = '/js/*';
            $paths[] = '/build/assets/*.js';
        }

        if ($this->option('images')) {
            $paths[] = '/images/*';
            $paths[] = '/storage/images/*';
        }

        return array_unique($paths);
    }
}

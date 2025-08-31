<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class GenerateAllApiDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:docs:generate-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Swagger documentation for all API versions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating API documentation for all versions...');

        // Get all API versions
        $versions = $this->getApiVersions();

        if (empty($versions)) {
            $this->warn('No API versions found in routes/api directory.');

            return;
        }

        $this->info('Found API versions: ' . implode(', ', $versions));

        // Generate documentation for each version
        foreach ($versions as $version) {
            $this->info("Generating documentation for {$version}...");

            try {
                Artisan::call('l5-swagger:generate', [
                    'documentation' => $version,
                ]);

                $this->info("✓ Documentation generated for {$version}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to generate documentation for {$version}: " . $e->getMessage());
            }
        }

        $this->info('API documentation generation completed!');
        $this->info('Available documentation URLs:');

        foreach ($versions as $version) {
            $this->line("  - {$version}: /api/documentation/{$version}");
        }
    }

    /**
     * Get all API versions by scanning the routes/api directory
     */
    protected function getApiVersions(): array
    {
        $versions = [];
        $routesPath = base_path('routes/api');

        if (File::exists($routesPath)) {
            $files = File::files($routesPath);

            foreach ($files as $file) {
                $filename = $file->getFilename();
                // Extract version from filename (e.g., v1.php -> v1)
                if (preg_match('/^v(\d+)\.php$/', $filename, $matches)) {
                    $versions[] = 'v' . $matches[1];
                }
            }
        }

        // Sort versions numerically
        usort($versions, function ($a, $b) {
            $aNum = (int) substr($a, 1);
            $bNum = (int) substr($b, 1);

            return $aNum - $bNum;
        });

        return $versions;
    }
}

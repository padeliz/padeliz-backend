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
    public function handle(): int
    {
        $this->info('Generating API documentation for all versions...');

        // Ensure the ApiDocumentationServiceProvider configuration is applied
        $this->ensureServiceProviderConfiguration();

        // Get all API versions
        $versions = $this->getApiVersions();

        if (empty($versions)) {
            $this->error('No API versions found in routes/api directory.');

            return self::FAILURE;
        }

        $this->info('Found API versions: ' . implode(', ', $versions));

        $failed = false;

        // Generate documentation for each version
        foreach ($versions as $version) {
            $this->info("Generating documentation for {$version}...");

            try {
                $exitCode = Artisan::call('l5-swagger:generate', [
                    'documentation' => $version,
                ]);

                if ($exitCode !== self::SUCCESS) {
                    $this->error("✗ Failed to generate documentation for {$version} (exit {$exitCode})");
                    $this->line(Artisan::output());
                    $failed = true;

                    continue;
                }

                $this->info("✓ Documentation generated for {$version}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to generate documentation for {$version}: " . $e->getMessage());
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('API documentation generation failed.');

            return self::FAILURE;
        }

        $this->info('API documentation generation completed!');
        $this->info('Available documentation URLs:');

        foreach ($versions as $version) {
            $this->line("  - {$version}: /api/documentation/{$version}");
        }

        return self::SUCCESS;
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

    /**
     * Ensure the ApiDocumentationService configuration is applied
     */
    protected function ensureServiceProviderConfiguration(): void
    {
        // Get the service instance and call its configureL5Swagger method
        $service = app(\App\Services\ApiDocumentationService::class);
        $service->configureL5Swagger();
    }
}

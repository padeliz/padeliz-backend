<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class ApiDocumentationService
{
    /**
     * Configure L5-Swagger with dynamic version detection and annotation isolation
     */
    public function configureL5Swagger(): void
    {
        // Get all API versions from route files
        $versions = $this->getApiVersions();

        if (empty($versions)) {
            return;
        }

        // Set the default version to the latest available version
        $latestVersion = end($versions);
        config(['l5-swagger.default' => $latestVersion]);

        // Configure each version's documentation
        foreach ($versions as $version) {
            $this->configureVersionDocumentation($version);
        }
    }

    /**
     * Get all API versions by scanning route files
     */
    public function getApiVersions(): array
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
     * Configure documentation for a specific version
     */
    public function configureVersionDocumentation(string $version): void
    {
        $configKey = "l5-swagger.documentations.{$version}";

        // Get controllers used by this version
        $controllers = $this->getControllersForVersion($version);

        // Configure the version
        config([
            $configKey => [
                'api' => [
                    'title' => "Padeliz API {$version}",
                ],
                'routes' => [
                    'api' => "api/documentation/{$version}",
                    'docs' => "api/documentation/{$version}/docs",
                    'oauth2_callback' => "api/documentation/{$version}/oauth2-callback",
                ],
                'paths' => [
                    'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),
                    'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
                    'docs_json' => "api-docs-{$version}.json",
                    'docs_yaml' => "api-docs-{$version}.yaml",
                    'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
                    'annotations' => $this->getAnnotationPathsForVersion($version, $controllers),
                ],
            ],
        ]);
    }

    /**
     * Get controllers used by a specific version by analyzing route files
     */
    public function getControllersForVersion(string $version): array
    {
        $controllers = [];
        $routeFile = base_path("routes/api/{$version}.php");

        if (! File::exists($routeFile)) {
            return $controllers;
        }

        $content = File::get($routeFile);

        // Extract controller class names from route definitions
        preg_match_all('/\[([^]]+Controller::class)/', $content, $matches);

        foreach ($matches[1] as $controller) {
            $controllers[] = $controller;
        }

        // Remove duplicates and sort
        $controllers = array_unique($controllers);
        sort($controllers);

        return $controllers;
    }

    /**
     * Get annotation paths for a specific version based on its controllers
     */
    public function getAnnotationPathsForVersion(string $version, array $controllers): array
    {
        $paths = [
            base_path('app/Http/Controllers/BaseVersions/' . strtoupper($version)),
            base_path('app/Http/Controllers/External'),
            // base_path("app/Http/GlobalAnnotationVersions/" . strtoupper($version)),
            base_path('app/Models'),
        ];

        // Add paths for controllers used by this version
        foreach ($controllers as $controller) {
            $controllerPath = $this->getControllerPath($controller);
            if ($controllerPath && ! in_array($controllerPath, $paths)) {
                $paths[] = $controllerPath;
            }
        }

        return $paths;
    }

    /**
     * Get the directory path for a controller class
     */
    public function getControllerPath(string $controllerClass): ?string
    {
        // Convert class name to file path
        $path = str_replace('\\', '/', $controllerClass);
        $path = str_replace('App/', 'app/', $path);
        $path = base_path($path);

        // Get the directory containing the controller
        $directory = dirname($path);

        return File::exists($directory) ? $directory : null;
    }
}

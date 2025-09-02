<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ApiDocumentationService;
use Illuminate\Support\ServiceProvider;

class ApiDocumentationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->configureL5Swagger();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Routes are automatically registered by L5-Swagger package
        // after our configuration is loaded in the register method
    }

    /**
     * Configure L5-Swagger with dynamic version detection and annotation isolation
     */
    protected function configureL5Swagger(): void
    {
        $service = app(ApiDocumentationService::class);
        $service->configureL5Swagger();
    }
}

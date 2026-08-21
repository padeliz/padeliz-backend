<?php

declare(strict_types=1);

namespace App\Providers;

use App\Integrations\RankedinBpt\BptDivisionMapper;
use App\Integrations\RankedinBpt\BptTopPlayersService;
use App\Integrations\RankedinBpt\Contracts\BptTopPlayersReportBuilder;
use App\Integrations\RankedinBpt\RankedinBptClient;
use App\Services\PlayerCustomField\Formats\GoogleFormMiniTourWelcomeCsvFormat;
use App\Services\PlayerCustomField\PlayerCustomFieldValueCsvFormatRegistry;
use App\Services\PlayerImport\Formats\PlaytomicPlayerCsvFormat;
use App\Services\PlayerImport\PlayerCsvFormatRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(BptTopPlayersReportBuilder::class, function (): BptTopPlayersService {
            /** @var array<string, mixed> $cfg */
            $cfg = config('rankedin_bpt');
            $client = new RankedinBptClient(
                (string) $cfg['base_url'],
                (int) $cfg['http_timeout_seconds'],
            );

            return new BptTopPlayersService($client, new BptDivisionMapper(), $cfg);
        });

        $this->app->singleton(PlayerCsvFormatRegistry::class, function (): PlayerCsvFormatRegistry {
            return new PlayerCsvFormatRegistry([
                new PlaytomicPlayerCsvFormat(),
            ]);
        });

        $this->app->singleton(PlayerCustomFieldValueCsvFormatRegistry::class, function (): PlayerCustomFieldValueCsvFormatRegistry {
            return new PlayerCustomFieldValueCsvFormatRegistry([
                new GoogleFormMiniTourWelcomeCsvFormat(),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }
}

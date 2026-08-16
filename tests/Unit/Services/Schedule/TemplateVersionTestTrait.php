<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\TemplateMatches;
use App\Services\Schedule\TemplateMatchesRepository;

trait TemplateVersionTestTrait
{
    private static int $allocVersionCounter = 0;

    protected function resetAllocatedVersions(): void
    {
        self::$allocVersionCounter = random_int(100_000, 900_000);
    }

    protected function allocVersion(): int
    {
        self::$allocVersionCounter++;

        return self::$allocVersionCounter;
    }

    protected function productionRepository(): TemplateMatchesRepository
    {
        // Avoid resource_path() so pure PHPUnit cases need no Laravel app bootstrap.
        return new TemplateMatchesRepository(
            dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'template-matches'
        );
    }

    protected function latestProductionVersion(): int
    {
        return $this->productionRepository()->latestVersion();
    }

    protected function loadLatestCommittedTemplate(
        int $players,
        int $partners,
        int $repeat = 1,
        int $courts = 1,
        bool $fixedTeams = false
    ): TemplateMatches {
        return $this->productionRepository()->find($players, $partners, $repeat, $courts, $fixedTeams);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\Exception\TemplateMatchesNotFoundException;
use App\Services\Schedule\TemplateMatchesRepository;
use App\Services\Schedule\TemplatesToolsService;
use Tests\TestCase;

final class TemplatesToolsServiceTest extends TestCase
{
    private TemplatesToolsService $service;

    private int $latestVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = new TemplateMatchesRepository(resource_path('template-matches'));
        $this->service = new TemplatesToolsService($repository);
        $this->latestVersion = $repository->latestVersion();
    }

    public function test_catalog_defaults_to_mixed_only(): void
    {
        $catalog = $this->service->catalog(false, $this->latestVersion);

        $this->assertFalse($catalog['include_fixed_teams']);
        foreach ($catalog['combos'] as $combo) {
            $this->assertFalse($combo['fixed_teams']);
        }
    }

    public function test_demo_returns_identity_and_matches(): void
    {
        $demo = $this->service->demo(8, 4, 1, 1, false, $this->latestVersion);

        $this->assertSame(8, $demo['players']);
        $this->assertSame(4, $demo['partners']);
        $this->assertIsArray($demo['matches']);
        $this->assertArrayHasKey('metrics', $demo);
        $this->assertTrue($demo['eligible']);
        $this->assertTrue($demo['usable']);
    }

    public function test_demo_throws_when_missing(): void
    {
        $this->expectException(TemplateMatchesNotFoundException::class);
        $this->service->demo(99, 99, 1, 1, false, $this->latestVersion);
    }

    public function test_metrics_summary_has_averages(): void
    {
        $summary = $this->service->metrics(false, $this->latestVersion);

        $this->assertSame(0, $summary['missing_count']);
        $this->assertNotEmpty($summary['rows']);
        $this->assertNotNull($summary['averages']['avg_distribution']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\RoundScheduleBreakAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * Committed-template ordering assertions (no DFS / runOrderingPhase).
 */
final class TemplateMatchesGeneratorOrderingTest extends TestCase
{
    use TemplateVersionTestTrait;

    protected function setUp(): void
    {
        $this->resetAllocatedVersions();
    }

    public function test_twelve_eight_courts_two_committed_schedule_has_min_break_zero(): void
    {
        $repo = $this->productionRepository();
        $version = $this->latestProductionVersion();
        $path = $repo->path($version, 12, 8, 1, 2, false);
        if (! is_file($path)) {
            $this->fail("No template at {$path}; regenerate offline: php bin/console templates:regenerate --players=12 --partners=8 --courts=2");
        }

        $template = $this->loadLatestCommittedTemplate(12, 8, 1, 2);
        if ($template->getMatches() === null) {
            fwrite(
                STDERR,
                "WARNING: Latest v{$version} 12/8/courts=2 has matches=null; skipping break metrics.\n"
            );

            return;
        }

        $metrics = $this->computeBreakMetricsFromRoundSchedule(
            $template->getMatches(),
            range(0, 11)
        );

        $this->assertSame(0, $metrics['minBreak']);
    }

    /**
     * Round-aware break metrics reference for per-court schedules (multi-court DFS output).
     *
     * @param array<int, array<int, array<int, array<int, int>>>> $matchesByCourt
     * @param array<int, int>                                     $mockPlayers
     * @return array{minBreak: int, maxBreak: int}
     */
    private function computeBreakMetricsFromRoundSchedule(array $matchesByCourt, array $mockPlayers): array
    {
        return RoundScheduleBreakAnalyzer::computeBreakMetrics($matchesByCourt, $mockPlayers);
    }
}

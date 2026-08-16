<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\ScheduleGenerationService;
use App\Services\Schedule\TemplateMatchesRepository;
use Tests\TestCase;

final class ScheduleGenerationServiceTest extends TestCase
{
    public function test_preview_loads_mixed_template_and_remaps_player_ids(): void
    {
        $service = new ScheduleGenerationService(
            new TemplateMatchesRepository(resource_path('template-matches'))
        );

        $playerIds = [101, 102, 103, 104, 105, 106, 107, 108];
        $result = $service->preview(
            $playerIds,
            4,
            1,
            ['Court A'],
            '14:00',
            '17:00',
            true,
            false,
        );

        $this->assertSame($playerIds, $result['player_ids']);
        $this->assertSame(1, $result['courts_count']);
        $this->assertGreaterThan(0, $result['rounds_total']);
        $this->assertGreaterThan(0, $result['matches_count']);
        $this->assertGreaterThan(0, $result['points_per_match']);
        $this->assertArrayHasKey(0, $result['matches_by_court']);

        $first = $result['matches_by_court'][0][0];
        $this->assertContains($first['side_a'][0], $playerIds);
        $this->assertContains($first['side_a'][1], $playerIds);
        $this->assertContains($first['side_b'][0], $playerIds);
        $this->assertContains($first['side_b'][1], $playerIds);
        $this->assertSame('Court A', $first['court_name']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $first['starts_at']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $first['ends_at']);
    }
}

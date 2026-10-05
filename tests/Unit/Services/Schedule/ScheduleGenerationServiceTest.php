<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\AveragePlayingTimeCalculator;
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

        $repo = new TemplateMatchesRepository(resource_path('template-matches'));
        $expectedFile = $repo->filenameForIdentity(8, 4, 1, 1, false);
        $this->assertSame($expectedFile, $result['template_file']);
        $this->assertArrayHasKey('average_playing_time_minutes', $result);
        $this->assertSame(
            AveragePlayingTimeCalculator::calculate('14:00', '17:00', (int) $result['rounds_total']),
            $result['average_playing_time_minutes']
        );
    }

    public function test_average_playing_time_ignores_final_and_demonstrative_flags(): void
    {
        $service = new ScheduleGenerationService(
            new TemplateMatchesRepository(resource_path('template-matches'))
        );

        $playerIds = [101, 102, 103, 104, 105, 106, 107, 108];
        $base = $service->preview($playerIds, 4, 1, ['Court A'], '14:00', '17:00', false, false);
        $withFinal = $service->preview($playerIds, 4, 1, ['Court A'], '14:00', '17:00', true, false);
        $withDemo = $service->preview($playerIds, 4, 1, ['Court A'], '14:00', '17:00', false, true);

        $this->assertSame($base['rounds_total'], $withFinal['rounds_total']);
        $this->assertSame($base['rounds_total'], $withDemo['rounds_total']);
        $this->assertSame(
            $base['average_playing_time_minutes'],
            $withFinal['average_playing_time_minutes']
        );
        $this->assertSame(
            $base['average_playing_time_minutes'],
            $withDemo['average_playing_time_minutes']
        );
    }
}

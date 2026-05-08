<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\RankedinBpt;

use App\Integrations\RankedinBpt\BptDivisionMapper;
use App\Integrations\RankedinBpt\BptTopPlayersService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Helpers\RankedinBptTestHttp;

final class BptTopPlayersServiceInternalTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): BptTopPlayersService
    {
        return new BptTopPlayersService(
            RankedinBptTestHttp::clientFromJsonQueue([]),
            new BptDivisionMapper(),
            [
                'base_url' => 'http://rankedin.test',
                'organisation_id' => 1,
                'ranking_id' => 15792,
                'men_ranking_type' => 3,
                'men_age_group' => 82,
                'women_ranking_type' => 4,
                'women_age_group' => 83,
                'max_concurrent_requests' => 1,
                'default_take' => 20,
                'max_take' => 100,
                'organisation_events_page_size' => 50,
                'rankings_page_size' => 100,
                'http_timeout_seconds' => 30,
            ]
        );
    }

    public function test_parse_playoffs_reads_elimination_from_object_root(): void
    {
        $svc = $this->service();
        $m = new ReflectionMethod(BptTopPlayersService::class, 'parsePlayoffsParticipantIds');
        $m->setAccessible(true);
        $decoded = [
            'Elimination' => [
                'FirstRoundParticipantCells' => [
                    ['EventParticipantId' => 10],
                    ['EventParticipantId' => 20],
                ],
            ],
        ];
        /** @var array<int, true> $set */
        $set = $m->invoke($svc, $decoded);

        self::assertTrue($set[10]);
        self::assertTrue($set[20]);
    }

    public function test_parse_playoffs_reads_elimination_from_first_list_element(): void
    {
        $svc = $this->service();
        $m = new ReflectionMethod(BptTopPlayersService::class, 'parsePlayoffsParticipantIds');
        $m->setAccessible(true);
        $decoded = [
            [
                'Elimination' => [
                    'FirstRoundParticipantCells' => [
                        ['EventParticipantId' => 7],
                    ],
                ],
            ],
        ];
        /** @var array<int, true> $set */
        $set = $m->invoke($svc, $decoded);

        self::assertTrue($set[7]);
    }

    public function test_class_has_playoffs_draw_detects_stage_one(): void
    {
        $svc = $this->service();
        $m = new ReflectionMethod(BptTopPlayersService::class, 'classHasPlayoffsDraw');
        $m->setAccessible(true);
        $draws = [
            [
                'Id' => 5,
                'TournamentDraws' => [
                    ['Stage' => 0],
                    ['Stage' => 1],
                ],
            ],
        ];
        self::assertTrue($m->invoke($svc, $draws, 5));
        self::assertFalse($m->invoke($svc, $draws, 99));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\RankedinBpt;

use App\Integrations\RankedinBpt\BptCanonicalDivision;
use App\Integrations\RankedinBpt\BptDivisionMapper;
use App\Integrations\RankedinBpt\BptTopPlayersService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\RankedinBptTestHttp;

final class BptTopPlayersServiceBuildTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2024-06-15 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseCfg(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadRanking(): array
    {
        return [
            'RankingDate' => '2024-06-01T00:00:00',
            'DoublesAgeGroups' => [
                ['AgeGroupID' => 82, 'AgeGroupText' => 'Men-Main'],
                ['AgeGroupID' => 83, 'AgeGroupText' => 'Women-Main'],
            ],
            'RankingAgeGroupPair' => [
                ['RankingTypeID' => 3, 'RankingTypeText' => 'Men-Doubles'],
                ['RankingTypeID' => 4, 'RankingTypeText' => 'Women-Doubles'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function orgEmpty(): array
    {
        return ['totalCount' => 0, 'payload' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private static function orgOneEvent(int $eventId = 10): array
    {
        return [
            'totalCount' => 1,
            'payload' => [
                [
                    'eventId' => $eventId,
                    'eventName' => 'Test Open',
                    'startDate' => '2024-01-01',
                    'endDate' => '2024-01-05',
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $classes
     * @return array<string, mixed>
     */
    private static function tournamentInfo(int $tournamentId, array $classes, int $persons = 10): array
    {
        return [
            'TournamentSidebarModel' => [
                'TotalUniquePersonsInTournament' => $persons,
                'StartDate' => '2024-01-02',
                'EndDate' => '2024-01-04',
                'Classes' => $classes,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function drawsForClasses(array $rows): array
    {
        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $dataRows
     * @return array<string, mixed>
     */
    private static function resultsMenD1(array $dataRows): array
    {
        return [
            'AgeGroup' => 82,
            'MatchType' => 3,
            'Data' => $dataRows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dataRows
     * @return array<string, mixed>
     */
    private static function resultsWomenD1(array $dataRows): array
    {
        return [
            'AgeGroup' => 83,
            'MatchType' => 4,
            'Data' => $dataRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function rankingsChunk(int $totalCount, array $payload): array
    {
        return [
            'TotalCount' => $totalCount,
            'Payload' => $payload,
        ];
    }

    public function test_build_with_no_finished_events_returns_empty_events_and_seven_division_buckets(): void
    {
        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgEmpty(),
        ]);
        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(20, false);

        self::assertSame([], $out['events']);
        self::assertCount(7, $out['divisionsRanking']);
        foreach ($out['divisionsRanking'] as $block) {
            self::assertArrayHasKey('divisionName', $block);
            self::assertSame([], $block['players']);
        }
        self::assertArrayNotHasKey('debug', $out);
    }

    public function test_build_men_division_one_with_playoffs_attaches_points_and_playoff_flags(): void
    {
        $dataRow = [
            'EventParticipantId' => 9001,
            'Standing' => 1,
            'RankingPoint' => ['Points' => 22.5],
            'Player1ParticipantId' => 101,
            'Player1Name' => 'Ace',
            'Player1Url' => 'https://rankedin.com/player/R-ace/',
            'Player2ParticipantId' => 102,
            'Player2Name' => 'Deuce',
            'Player2Url' => 'https://rankedin.com/player/R-deuce/',
        ];

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 99, 'Name' => 'Men Division 1']]),
            self::drawsForClasses([
                [
                    'Id' => 99,
                    'TournamentDraws' => [
                        ['Stage' => 0],
                        ['Stage' => 1],
                    ],
                ],
            ]),
            self::resultsMenD1([$dataRow]),
            [
                'Elimination' => [
                    'FirstRoundParticipantCells' => [
                        ['EventParticipantId' => 9001],
                    ],
                ],
            ],
            self::rankingsChunk(2, [
                ['RankedinId' => 'R-ace', 'ParticipantPoints' => ['Points' => 300.0]],
                ['RankedinId' => 'R-deuce', 'ParticipantPoints' => ['Points' => 150.0]],
            ]),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(20, false);

        self::assertCount(1, $out['events']);
        $d1Men = null;
        foreach ($out['divisionsRanking'] as $block) {
            if ($block['divisionName'] === BptCanonicalDivision::Division1Men->value) {
                $d1Men = $block;

                break;
            }
        }
        self::assertNotNull($d1Men);
        self::assertCount(2, $d1Men['players']);
        $byId = [];
        foreach ($d1Men['players'] as $row) {
            $byId[(string) $row['rankedinId']] = $row;
        }
        self::assertSame(300.0, $byId['R-ace']['bptTotalRankingPoints']);
        self::assertSame(150.0, $byId['R-deuce']['bptTotalRankingPoints']);
        $acePart = $byId['R-ace']['divisionsParticipatedInto'][0];
        self::assertArrayNotHasKey('eventId', $acePart);
        self::assertArrayNotHasKey('startDate', $acePart);
        self::assertArrayNotHasKey('endDate', $acePart);
        self::assertTrue($acePart['gotIntoPlayoffs']);
        self::assertSame(22.5, $acePart['earnedPoints']);
        $tm = $acePart['teamMate'];
        self::assertSame('R-deuce', $tm['rankedinId']);
        self::assertSame('Deuce', $tm['name']);
        self::assertSame(150.0, $tm['bptTotalRankingPoints']);
        self::assertSame(2, $tm['divisionRankingNumber']);
        self::assertArrayNotHasKey('playerId', $byId['R-ace']);
        self::assertArrayNotHasKey('teamMates', $byId['R-ace']);
    }

    public function test_build_women_division_one_without_playoffs(): void
    {
        $row = [
            'EventParticipantId' => 8001,
            'Standing' => 2,
            'RankingPoint' => ['Points' => 5.0],
            'Player1ParticipantId' => 201,
            'Player1Name' => 'Ann',
            'Player1Url' => 'https://rankedin.com/player/R-ann/',
            'Player2ParticipantId' => 202,
            'Player2Name' => 'Bea',
            'Player2Url' => 'https://rankedin.com/player/R-bea/',
        ];

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 55, 'Name' => 'Women Division 1']]),
            self::drawsForClasses([
                ['Id' => 55, 'TournamentDraws' => [['Stage' => 0]]],
            ]),
            self::resultsWomenD1([$row]),
            self::rankingsChunk(0, []),
            self::rankingsChunk(2, [
                ['RankedinId' => 'R-ann', 'ParticipantPoints' => ['Points' => 88.0]],
                ['RankedinId' => 'R-bea', 'ParticipantPoints' => ['Points' => 120.0]],
            ]),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(20, false);

        $d1w = null;
        foreach ($out['divisionsRanking'] as $block) {
            if ($block['divisionName'] === BptCanonicalDivision::Division1Women->value) {
                $d1w = $block;

                break;
            }
        }
        self::assertNotNull($d1w);
        self::assertCount(2, $d1w['players']);
        $first = $d1w['players'][0];
        self::assertSame(1, $first['divisionRankingNumber']);
        self::assertSame(120.0, $first['bptTotalRankingPoints']);
        $part = $first['divisionsParticipatedInto'][0];
        self::assertFalse($part['gotIntoPlayoffs']);
        $tm = $part['teamMate'];
        self::assertSame('R-ann', $tm['rankedinId']);
        self::assertSame(88.0, $tm['bptTotalRankingPoints']);
        self::assertSame(2, $tm['divisionRankingNumber']);
    }

    public function test_build_skips_junior_class_without_calling_get_results(): void
    {
        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 1, 'Name' => 'Junior Academy']]),
            self::drawsForClasses([]),
            self::rankingsChunk(0, []),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(5, false);

        self::assertSame(6, $client->callCount());
        self::assertCount(1, $out['events']);
        foreach ($out['divisionsRanking'] as $block) {
            self::assertSame([], $block['players']);
        }
    }

    public function test_build_uses_get_players_for_class_when_results_data_empty(): void
    {
        $playersForClass = [
            'Participants' => [
                [
                    'Participant' => [
                        'EventParticipantId' => 77,
                        'FirstPlayer' => [
                            'Id' => 1,
                            'Name' => 'P1',
                            'PlayerUrl' => 'https://rankedin.com/player/R-p1/',
                        ],
                        'SecondPlayer' => [
                            'Id' => 2,
                            'Name' => 'P2',
                            'PlayerUrl' => 'https://rankedin.com/player/R-p2/',
                        ],
                    ],
                ],
            ],
        ];

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 40, 'Name' => 'Men Division 2']]),
            self::drawsForClasses([
                ['Id' => 40, 'TournamentDraws' => []],
            ]),
            self::resultsMenD1([]),
            $playersForClass,
            self::rankingsChunk(2, [
                ['RankedinId' => 'R-p1', 'ParticipantPoints' => ['Points' => 10.0]],
                ['RankedinId' => 'R-p2', 'ParticipantPoints' => ['Points' => 20.0]],
            ]),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(20, false);

        $d2 = null;
        foreach ($out['divisionsRanking'] as $block) {
            if ($block['divisionName'] === BptCanonicalDivision::Division2Men->value) {
                $d2 = $block;

                break;
            }
        }
        self::assertNotNull($d2);
        self::assertCount(2, $d2['players']);
    }

    public function test_build_mixed_match_type_does_not_aggregate_players(): void
    {
        $row = [
            'EventParticipantId' => 1,
            'Standing' => 1,
            'RankingPoint' => ['Points' => 99.0],
            'Player1ParticipantId' => 1,
            'Player1Name' => 'A',
            'Player1Url' => 'https://rankedin.com/player/R-a/',
            'Player2ParticipantId' => 2,
            'Player2Name' => 'B',
            'Player2Url' => 'https://rankedin.com/player/R-b/',
        ];

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 9, 'Name' => 'Men Division 1']]),
            self::drawsForClasses([['Id' => 9, 'TournamentDraws' => [['Stage' => 1]]]]),
            [
                'AgeGroup' => 82,
                'MatchType' => 5,
                'Data' => [$row],
            ],
            self::rankingsChunk(0, []),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(20, false);

        foreach ($out['divisionsRanking'] as $block) {
            self::assertSame([], $block['players']);
        }
    }

    public function test_build_paginates_organisation_events_across_two_pages(): void
    {
        $cfg = $this->baseCfg();
        $cfg['organisation_events_page_size'] = 1;

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            [
                'totalCount' => 2,
                'payload' => [
                    [
                        'eventId' => 10,
                        'eventName' => 'A',
                        'startDate' => '2024-01-01',
                        'endDate' => '2024-01-02',
                    ],
                ],
            ],
            [
                'totalCount' => 2,
                'payload' => [
                    [
                        'eventId' => 11,
                        'eventName' => 'B',
                        'startDate' => '2024-02-01',
                        'endDate' => '2024-02-02',
                    ],
                ],
            ],
            self::tournamentInfo(10, []),
            self::drawsForClasses([]),
            self::tournamentInfo(11, []),
            self::drawsForClasses([]),
            self::rankingsChunk(0, []),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $cfg);
        $out = $svc->build(5, false);

        self::assertCount(2, $out['events']);
    }

    public function test_build_paginates_rankings_until_total_exhausted(): void
    {
        $cfg = $this->baseCfg();
        $cfg['rankings_page_size'] = 100;

        $menPage1 = [];
        for ($i = 0; $i < 100; $i++) {
            $menPage1[] = ['RankedinId' => 'R-m' . $i, 'ParticipantPoints' => ['Points' => 1.0]];
        }
        $menPage2 = [];
        for ($i = 100; $i < 200; $i++) {
            $menPage2[] = ['RankedinId' => 'R-m' . $i, 'ParticipantPoints' => ['Points' => 1.0]];
        }
        $menPage3 = [];
        for ($i = 200; $i < 250; $i++) {
            $menPage3[] = ['RankedinId' => 'R-m' . $i, 'ParticipantPoints' => ['Points' => 1.0]];
        }

        $row = [
            'EventParticipantId' => 1,
            'Standing' => 1,
            'RankingPoint' => ['Points' => 1.0],
            'Player1ParticipantId' => 1,
            'Player1Name' => 'X',
            'Player1Url' => 'https://rankedin.com/player/R-m199/',
            'Player2ParticipantId' => 2,
            'Player2Name' => 'Y',
            'Player2Url' => 'https://rankedin.com/player/R-m0/',
        ];

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 99, 'Name' => 'Men Division 1']]),
            self::drawsForClasses([['Id' => 99, 'TournamentDraws' => []]]),
            self::resultsMenD1([$row]),
            self::rankingsChunk(250, $menPage1),
            self::rankingsChunk(250, $menPage2),
            self::rankingsChunk(250, $menPage3),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $cfg);
        $svc->build(5, false);

        self::assertGreaterThanOrEqual(3, $client->callCount());
    }

    public function test_build_take_limits_players_per_division_and_sorts_by_points_then_name(): void
    {
        $rows = [];
        $names = ['Charlie', 'Alice', 'Bob'];
        foreach ($names as $idx => $name) {
            $slug = 'R-player-' . $idx;
            $rows[] = [
                'EventParticipantId' => 5000 + $idx,
                'Standing' => $idx + 1,
                'RankingPoint' => ['Points' => 10.0],
                'Player1ParticipantId' => $idx * 10 + 1,
                'Player1Name' => $name,
                'Player1Url' => 'https://rankedin.com/player/' . $slug . '/',
                'Player2ParticipantId' => $idx * 10 + 2,
                'Player2Name' => 'Mate ' . $name,
                'Player2Url' => 'https://rankedin.com/player/R-mate-' . $idx . '/',
            ];
        }

        $rankMen = [];
        foreach ($names as $idx => $name) {
            $slug = 'R-player-' . $idx;
            $pts = 50.0;
            $rankMen[] = ['RankedinId' => $slug, 'ParticipantPoints' => ['Points' => $pts]];
            $rankMen[] = ['RankedinId' => 'R-mate-' . $idx, 'ParticipantPoints' => ['Points' => 1.0]];
        }

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 99, 'Name' => 'Men Division 1']]),
            self::drawsForClasses([['Id' => 99, 'TournamentDraws' => []]]),
            self::resultsMenD1($rows),
            self::rankingsChunk(count($rankMen), $rankMen),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(2, false);

        $d1Men = null;
        foreach ($out['divisionsRanking'] as $block) {
            if ($block['divisionName'] === BptCanonicalDivision::Division1Men->value) {
                $d1Men = $block;

                break;
            }
        }
        self::assertNotNull($d1Men);
        self::assertCount(2, $d1Men['players']);
        self::assertSame('Alice', $d1Men['players'][0]['name']);
        self::assertSame('Bob', $d1Men['players'][1]['name']);
    }

    public function test_build_debug_includes_mappings_and_http_call_counter(): void
    {
        $row = [
            'EventParticipantId' => 1,
            'Standing' => 1,
            'RankingPoint' => ['Points' => 1.0],
            'Player1ParticipantId' => 1,
            'Player1Name' => 'A',
            'Player1Url' => 'https://rankedin.com/player/R-a/',
            'Player2ParticipantId' => 2,
            'Player2Name' => 'B',
            'Player2Url' => 'https://rankedin.com/player/R-b/',
        ];

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 99, 'Name' => 'Men Division 1']]),
            self::drawsForClasses([['Id' => 99, 'TournamentDraws' => []]]),
            self::resultsMenD1([$row]),
            self::rankingsChunk(2, [
                ['RankedinId' => 'R-a', 'ParticipantPoints' => ['Points' => 10.0]],
                ['RankedinId' => 'R-b', 'ParticipantPoints' => ['Points' => 10.0]],
            ]),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(20, true);

        self::assertArrayHasKey('debug', $out);
        self::assertArrayHasKey('classMappings', $out['debug']);
        self::assertArrayHasKey('rankedinHttpCalls', $out['debug']);
        self::assertGreaterThan(0, $out['debug']['rankedinHttpCalls']);
    }

    public function test_build_team_mate_on_participation_uses_name_when_not_in_rankings_map(): void
    {
        $row = [
            'EventParticipantId' => 1,
            'Standing' => 1,
            'RankingPoint' => ['Points' => 1.0],
            'Player1ParticipantId' => 1,
            'Player1Name' => 'Solo',
            'Player1Url' => 'https://rankedin.com/player/R-solo/',
            'Player2ParticipantId' => 2,
            'Player2Name' => 'Ghost Partner',
            'Player2Url' => 'https://rankedin.com/player/F-ghost/',
        ];

        $client = RankedinBptTestHttp::clientFromJsonQueue([
            self::loadRanking(),
            self::orgOneEvent(10),
            self::tournamentInfo(10, [['Id' => 99, 'Name' => 'Men Division 1']]),
            self::drawsForClasses([['Id' => 99, 'TournamentDraws' => []]]),
            self::resultsMenD1([$row]),
            self::rankingsChunk(1, [
                ['RankedinId' => 'R-solo', 'ParticipantPoints' => ['Points' => 500.0]],
            ]),
            self::rankingsChunk(0, []),
        ]);

        $svc = new BptTopPlayersService($client, new BptDivisionMapper(), $this->baseCfg());
        $out = $svc->build(20, false);

        $d1Men = null;
        foreach ($out['divisionsRanking'] as $block) {
            if ($block['divisionName'] === BptCanonicalDivision::Division1Men->value) {
                $d1Men = $block;

                break;
            }
        }
        self::assertNotNull($d1Men);
        $solo = $d1Men['players'][0];
        self::assertArrayNotHasKey('teamMates', $solo);
        $tm = $solo['divisionsParticipatedInto'][0]['teamMate'];
        self::assertSame('F-ghost', $tm['rankedinId']);
        self::assertSame('Ghost Partner', $tm['name']);
        self::assertSame(0.0, $tm['bptTotalRankingPoints']);
        self::assertSame(2, $tm['divisionRankingNumber']);
    }
}

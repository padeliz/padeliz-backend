<?php

declare(strict_types=1);

namespace App\Integrations\RankedinBpt;

use App\Integrations\RankedinBpt\Contracts\BptTopPlayersReportBuilder;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class BptTopPlayersService implements BptTopPlayersReportBuilder
{
    public function __construct(
        private readonly RankedinBptClient $client,
        private readonly BptDivisionMapper $mapper,
        /** @var array<string, mixed> */
        private readonly array $cfg,
    ) {
    }

    /**
     * @return array{events: list<array<string, mixed>>, divisionsRanking: list<array<string, mixed>>, debug?: array<string, mixed>}
     */
    public function build(int $take, bool $debug): array
    {
        $take = min((int) $this->cfg['max_take'], max(1, $take));
        $concurrency = (int) $this->cfg['max_concurrent_requests'];
        $rankingId = (int) $this->cfg['ranking_id'];
        $orgId = (int) $this->cfg['organisation_id'];

        $debugPayload = $debug ? [
            'classMappings' => [],
            'errors' => [],
            'rankedinHttpCalls' => 0,
        ] : null;

        $loadRanking = $this->fetchLoadRankingInfo($rankingId);
        if ($debugPayload !== null) {
            $debugPayload['rankedinHttpCalls'] = $this->client->callCount();
        }

        $taxonomy = $this->mapper->taxonomyFromLoadRankingInfo($loadRanking);
        $rankingDate = $this->rankingDateString($loadRanking['RankingDate'] ?? null);

        $finishedEvents = $this->fetchAllFinishedOrganisationEvents($orgId);
        if ($finishedEvents === []) {
            $out = [
                'events' => [],
                'divisionsRanking' => $this->emptyDivisionsRanking(),
            ];
            if ($debugPayload !== null) {
                $out['debug'] = $debugPayload + ['note' => 'No finished events returned'];
            }

            return $out;
        }

        $tournamentIds = array_values(array_unique(array_map(static fn ($e) => (int) ($e['eventId'] ?? 0), $finishedEvents)));

        /** @var array<int, array<string, mixed>> $infoByTournament */
        $infoByTournament = [];
        /** @var array<int, list<array<string, mixed>>> $drawsByTournament */
        $drawsByTournament = [];

        $metaItems = [];
        foreach ($tournamentIds as $tid) {
            $metaItems[] = [
                'key' => 'info:' . $tid,
                'path' => 'v1/tournament/GetInfoAsync',
                'query' => ['id' => $tid, 'language' => 'en'],
            ];
            $metaItems[] = [
                'key' => 'draws:' . $tid,
                'path' => 'v1/tournament/GetClassesAndDrawNamesAsync/',
                'query' => ['tournamentId' => $tid],
            ];
        }

        try {
            $this->client->getJsonPool(
                $metaItems,
                $concurrency,
                function (string $key, array $decoded) use (&$infoByTournament, &$drawsByTournament, &$debugPayload): void {
                    if (str_starts_with($key, 'info:')) {
                        $tid = (int) Str::after($key, 'info:');
                        $infoByTournament[$tid] = $decoded;
                    } elseif (str_starts_with($key, 'draws:')) {
                        $tid = (int) Str::after($key, 'draws:');
                        $drawsByTournament[$tid] = is_array($decoded) ? $decoded : [];
                    }
                },
                function (string $key, \Throwable $e) use (&$debugPayload): void {
                    if ($debugPayload !== null) {
                        $debugPayload['errors'][] = ['key' => $key, 'message' => $e->getMessage()];
                    }
                }
            );
        } catch (\Throwable) {
            // Pool handles per-request failures via rejected handler
        }

        if ($debugPayload !== null) {
            $debugPayload['rankedinHttpCalls'] = $this->client->callCount();
        }

        $eventsOut = [];
        foreach ($finishedEvents as $ev) {
            $tid = (int) ($ev['eventId'] ?? 0);
            $info = $infoByTournament[$tid] ?? null;
            $sidebar = is_array($info) ? ($info['TournamentSidebarModel'] ?? null) : null;
            $participants = is_array($sidebar) ? (int) ($sidebar['TotalUniquePersonsInTournament'] ?? 0) : 0;
            $eventsOut[] = [
                'eventId' => $tid,
                'eventName' => (string) ($ev['eventName'] ?? ''),
                'startDate' => (string) ($ev['startDate'] ?? ($sidebar['StartDate'] ?? '')),
                'endDate' => (string) ($ev['endDate'] ?? ($sidebar['EndDate'] ?? '')),
                'participantsCount' => $participants,
            ];
        }

        $resultsItems = [];
        $classContext = [];

        foreach ($tournamentIds as $tid) {
            $info = $infoByTournament[$tid] ?? [];
            $sidebar = $info['TournamentSidebarModel'] ?? [];
            $classes = $sidebar['Classes'] ?? [];
            if (! is_array($classes)) {
                continue;
            }
            foreach ($classes as $classRow) {
                if (! is_array($classRow)) {
                    continue;
                }
                $classId = (int) ($classRow['Id'] ?? 0);
                $className = (string) ($classRow['Name'] ?? '');
                if ($classId === 0) {
                    continue;
                }
                if ($this->shouldSkipClassByLocalHeuristic($className)) {
                    if ($debugPayload !== null) {
                        $debugPayload['classMappings'][] = [
                            'tournamentId' => $tid,
                            'classId' => $classId,
                            'className' => $className,
                            'decision' => 'skipped_local_heuristic',
                        ];
                    }

                    continue;
                }
                $key = 'results:' . $tid . ':' . $classId;
                $resultsItems[] = [
                    'key' => $key,
                    'path' => 'v1/tournament/GetResultsAsync',
                    'query' => [
                        'tournamentId' => $tid,
                        'classId' => $classId,
                        'rankingId' => $rankingId,
                        'language' => 'en',
                    ],
                ];
                $hasPlayoffs = $this->classHasPlayoffsDraw($drawsByTournament[$tid] ?? [], $classId);
                $classContext[$key] = [
                    'tournamentId' => $tid,
                    'classId' => $classId,
                    'className' => $className,
                    'hasPlayoffs' => $hasPlayoffs,
                ];
            }
        }

        /** @var array<string, array<string, mixed>> $resultsByKey */
        $resultsByKey = [];
        $this->client->getJsonPool(
            $resultsItems,
            $concurrency,
            function (string $key, array $decoded) use (&$resultsByKey): void {
                $resultsByKey[$key] = $decoded;
            },
            function (string $key, \Throwable $e) use (&$resultsByKey, &$debugPayload): void {
                $resultsByKey[$key] = ['Data' => [], '_error' => $e->getMessage()];
                if ($debugPayload !== null) {
                    $debugPayload['errors'][] = ['key' => $key, 'message' => $e->getMessage()];
                }
            }
        );

        if ($debugPayload !== null) {
            $debugPayload['rankedinHttpCalls'] = $this->client->callCount();
        }

        $drawItems = [];
        $playersFallbackItems = [];

        /** @var array<string, BptCanonicalDivision> $canonicalByKey */
        $canonicalByKey = [];

        foreach ($resultsByKey as $key => $decoded) {
            $ctx = $classContext[$key] ?? null;
            if ($ctx === null) {
                continue;
            }
            $className = $ctx['className'];
            $ageGroup = (int) ($decoded['AgeGroup'] ?? 0);
            $matchType = (int) ($decoded['MatchType'] ?? 0);
            $canonical = $this->mapper->map($className, $ageGroup, $matchType);
            $ageText = $taxonomy['ageGroupTexts'][(string) $ageGroup] ?? null;
            $typeText = $taxonomy['rankingTypeTexts'][(string) ($decoded['MatchType'] ?? '')] ?? null;

            if ($debugPayload !== null) {
                $debugPayload['classMappings'][] = [
                    'tournamentId' => $ctx['tournamentId'],
                    'classId' => $ctx['classId'],
                    'className' => $className,
                    'ageGroupId' => $ageGroup,
                    'ageGroupText' => $ageText,
                    'matchTypeId' => $matchType,
                    'rankingTypeTextGuess' => $typeText,
                    'canonical' => $canonical?->value ?? 'ignored',
                    'reason' => $canonical === null ? 'mapper_returned_null' : 'mapped',
                ];
            }

            if ($canonical === null) {
                continue;
            }

            $canonicalByKey[$key] = $canonical;
            $data = $decoded['Data'] ?? [];
            if ($ctx['hasPlayoffs'] === true) {
                $dk = 'draw:' . $ctx['tournamentId'] . ':' . $ctx['classId'];
                $drawItems[] = [
                    'key' => $dk,
                    'path' => 'v1/tournament/GetDrawsForStageAndStrengthAsync',
                    'query' => [
                        'tournamentClassId' => $ctx['classId'],
                        'drawStrength' => 0,
                        'drawStage' => 1,
                        'isReadonly' => 'true',
                        'language' => 'en',
                    ],
                ];
                $classContext[$dk] = $ctx + ['resultsKey' => $key];
            }

            if (! is_array($data) || $data === []) {
                $pk = 'players:' . $ctx['tournamentId'] . ':' . $ctx['classId'];
                $playersFallbackItems[] = [
                    'key' => $pk,
                    'path' => 'v1/tournament/GetPlayersForClassAsync',
                    'query' => [
                        'tournamentId' => $ctx['tournamentId'],
                        'tournamentClassId' => $ctx['classId'],
                        'language' => 'en',
                    ],
                ];
                $classContext[$pk] = $ctx + ['resultsKey' => $key];
            }
        }

        /** @var array<string, array<int, true>> $playoffsParticipantIdsByKey */
        $playoffsParticipantIdsByKey = [];

        if ($drawItems !== []) {
            $this->client->getJsonPool(
                $drawItems,
                $concurrency,
                function (string $key, array $decoded) use (&$playoffsParticipantIdsByKey, $classContext, $debugPayload): void {
                    $ctx = $classContext[$key] ?? null;
                    $rk = is_array($ctx) ? (string) ($ctx['resultsKey'] ?? '') : '';
                    if ($rk === '') {
                        return;
                    }
                    $ids = $this->parsePlayoffsParticipantIds($decoded);
                    $playoffsParticipantIdsByKey[$rk] = $ids;
                },
                function (string $key, \Throwable $e) use (&$debugPayload): void {
                    if ($debugPayload !== null) {
                        $debugPayload['errors'][] = ['key' => $key, 'message' => $e->getMessage()];
                    }
                }
            );
        }

        /** @var array<string, array<string, mixed>> $playersByKey */
        $playersByKey = [];
        if ($playersFallbackItems !== []) {
            $this->client->getJsonPool(
                $playersFallbackItems,
                $concurrency,
                function (string $key, array $decoded) use (&$playersByKey): void {
                    $playersByKey[$key] = $decoded;
                },
                function (string $key, \Throwable $e) use (&$debugPayload): void {
                    if ($debugPayload !== null) {
                        $debugPayload['errors'][] = ['key' => $key, 'message' => $e->getMessage()];
                    }
                }
            );
        }

        if ($debugPayload !== null) {
            foreach (array_keys($playersByKey) as $pk) {
                $debugPayload['classMappings'][] = [
                    'key' => $pk,
                    'decision' => 'get_players_fallback_used',
                ];
            }
            $debugPayload['rankedinHttpCalls'] = $this->client->callCount();
        }

        /** @var array<string, array<string, mixed>> $eventMetaById */
        $eventMetaById = [];
        foreach ($eventsOut as $e) {
            $eventMetaById[(string) $e['eventId']] = $e;
        }

        /** @var array<string, array<string, mixed>> $playerByRankedinId */
        $playerByRankedinId = [];

        foreach ($resultsByKey as $key => $decoded) {
            $canonical = $canonicalByKey[$key] ?? null;
            if ($canonical === null) {
                continue;
            }
            $ctx = $classContext[$key] ?? null;
            if (! is_array($ctx)) {
                continue;
            }
            $tid = (int) $ctx['tournamentId'];
            $classId = (int) $ctx['classId'];
            $className = (string) $ctx['className'];
            $eventMeta = $eventMetaById[(string) $tid] ?? [
                'eventId' => $tid,
                'eventName' => '',
                'startDate' => '',
                'endDate' => '',
            ];

            $playoffsSet = $playoffsParticipantIdsByKey[$key] ?? [];
            $rows = $decoded['Data'] ?? [];
            $teams = $this->teamsFromResults($rows);
            if ($teams === []) {
                $pk = 'players:' . $tid . ':' . $classId;
                $pjson = $playersByKey[$pk] ?? null;
                if (is_array($pjson)) {
                    $teams = $this->teamsFromPlayersForClass($pjson);
                }
            }
            $teamsCount = count($teams);

            foreach ($teams as $team) {
                $eventParticipantId = (int) ($team['eventParticipantId'] ?? 0);
                $p1 = $team['player1'] ?? [];
                $p2 = $team['player2'] ?? [];
                $id1 = $this->playerKeyFromParticipantBlock($p1);
                $id2 = $this->playerKeyFromParticipantBlock($p2);
                if ($id1 === '' || $id2 === '') {
                    continue;
                }
                $name1 = is_array($p1) ? (string) ($p1['Player1Name'] ?? $p1['Name'] ?? '') : '';
                $name2 = is_array($p2) ? (string) ($p2['Player2Name'] ?? $p2['Name'] ?? '') : '';
                $standing = (int) ($team['standing'] ?? 0);
                $earned = (float) ($team['earnedPoints'] ?? 0.0);
                $gotPlayoffs = $eventParticipantId > 0 && isset($playoffsSet[$eventParticipantId]);

                $this->touchPlayer($playerByRankedinId, $id1, $name1);
                $this->touchPlayer($playerByRankedinId, $id2, $name2);

                $this->appendParticipation(
                    $playerByRankedinId[$id1],
                    $eventMeta,
                    $className,
                    $canonical,
                    $teamsCount,
                    $earned,
                    $gotPlayoffs,
                    $name2,
                    $id2,
                    $tid,
                    $classId
                );
                $this->appendParticipation(
                    $playerByRankedinId[$id2],
                    $eventMeta,
                    $className,
                    $canonical,
                    $teamsCount,
                    $earned,
                    $gotPlayoffs,
                    $name1,
                    $id1,
                    $tid,
                    $classId
                );
            }
        }

        $menType = (int) $this->cfg['men_ranking_type'];
        $menAge = (int) $this->cfg['men_age_group'];
        $womenType = (int) $this->cfg['women_ranking_type'];
        $womenAge = (int) $this->cfg['women_age_group'];

        $menPoints = $this->fetchAllRankingPoints($rankingId, $menType, $menAge, $rankingDate);
        $womenPoints = $this->fetchAllRankingPoints($rankingId, $womenType, $womenAge, $rankingDate);

        foreach ($playerByRankedinId as $rid => &$p) {
            $pts = $menPoints[$rid] ?? $womenPoints[$rid] ?? 0.0;
            $p['bptTotalRankingPoints'] = $pts;
        }
        unset($p);

        $divisionRanks = $this->computeDivisionRankingNumbers($playerByRankedinId);

        $divisionsRanking = [];
        foreach (BptCanonicalDivision::cases() as $division) {
            $playersInDivision = [];
            foreach ($playerByRankedinId as $rid => $p) {
                $has = false;
                foreach ($p['participations'] ?? [] as $part) {
                    if (($part['canonicalDivision'] ?? '') === $division->value) {
                        $has = true;

                        break;
                    }
                }
                if (! $has) {
                    continue;
                }
                $playersInDivision[] = $this->buildPlayerRow(
                    $rid,
                    $p,
                    $division,
                    $playerByRankedinId,
                    $divisionRanks,
                );
            }

            usort($playersInDivision, static function (array $a, array $b): int {
                $pa = (float) ($a['bptTotalRankingPoints'] ?? 0);
                $pb = (float) ($b['bptTotalRankingPoints'] ?? 0);
                if ($pa !== $pb) {
                    return $pb <=> $pa;
                }
                $na = (string) ($a['name'] ?? '');
                $nb = (string) ($b['name'] ?? '');
                if ($na !== $nb) {
                    return $na <=> $nb;
                }

                return strcmp((string) ($a['rankedinId'] ?? ''), (string) ($b['rankedinId'] ?? ''));
            });

            $playersInDivision = array_slice($playersInDivision, 0, $take);
            $pos = 1;
            foreach ($playersInDivision as &$row) {
                $row['divisionRankingNumber'] = $pos;
                ++$pos;
            }
            unset($row);

            $divisionsRanking[] = [
                'divisionName' => $division->value,
                'players' => $playersInDivision,
            ];
        }

        $out = [
            'events' => $eventsOut,
            'divisionsRanking' => $divisionsRanking,
        ];

        if ($debugPayload !== null) {
            $debugPayload['rankedinHttpCalls'] = $this->client->callCount();
            $out['debug'] = $debugPayload;
        }

        return $out;
    }

    /**
     * @return list<array{divisionName: string, players: list<array<string, mixed>>}>
     */
    private function emptyDivisionsRanking(): array
    {
        $out = [];
        foreach (BptCanonicalDivision::cases() as $division) {
            $out[] = ['divisionName' => $division->value, 'players' => []];
        }

        return $out;
    }

    private function fetchLoadRankingInfo(int $rankingId): array
    {
        $womenType = (int) $this->cfg['women_ranking_type'];
        $womenAge = (int) $this->cfg['women_age_group'];
        $week = (int) now()->isoWeek();
        $year = (int) now()->year;

        try {
            return $this->client->getJson('v1/ranking/LoadRankingInfoAsync', [
                'id' => $rankingId,
                'rankingType' => $womenType,
                'ageGroup' => $womenAge,
                'week' => $week,
                'year' => $year,
            ]);
        } catch (GuzzleException) {
            return $this->client->getJson('v1/ranking/LoadRankingInfoAsync', [
                'id' => $rankingId,
                'rankingType' => $womenType,
                'ageGroup' => $womenAge,
                'week' => max(1, $week - 1),
                'year' => $year,
            ]);
        }
    }

    private function rankingDateString(mixed $rankingDate): string
    {
        if (is_string($rankingDate) && $rankingDate !== '') {
            return Carbon::parse($rankingDate)->toDateString();
        }

        return now()->toDateString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllFinishedOrganisationEvents(int $organisationId): array
    {
        $pageSize = (int) $this->cfg['organisation_events_page_size'];
        $all = [];
        $skip = 0;
        $total = PHP_INT_MAX;

        while ($skip < $total) {
            $chunk = $this->client->getJson('v1/Organization/GetOrganisationEventsAsync', [
                'organisationId' => $organisationId,
                'IsFinished' => 'true',
                'Language' => 'en',
                'skip' => $skip,
                'take' => $pageSize,
            ]);
            $payload = $chunk['payload'] ?? [];
            $total = (int) ($chunk['totalCount'] ?? count($payload));
            if (is_array($payload)) {
                foreach ($payload as $row) {
                    if (is_array($row)) {
                        $all[] = $row;
                    }
                }
            }
            $skip += $pageSize;
            if (! is_array($payload) || $payload === []) {
                break;
            }
        }

        return $all;
    }

    /**
     * @param  list<array<string, mixed>>  $drawsResponse  root array from GetClassesAndDrawNamesAsync
     */
    private function classHasPlayoffsDraw(array $drawsResponse, int $classId): bool
    {
        foreach ($drawsResponse as $row) {
            if (! is_array($row) || (int) ($row['Id'] ?? 0) !== $classId) {
                continue;
            }
            foreach ($row['TournamentDraws'] ?? [] as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $stage = (int) ($d['Stage'] ?? -1);
                if ($stage === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function shouldSkipClassByLocalHeuristic(string $className): bool
    {
        $n = mb_strtolower($className);
        if (preg_match('/\b(junior|beginner)\b/u', $n) && ! preg_match('/\b(bpt|division|divizia|div)\b/u', $n)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{eventParticipantId: int, standing: int, earnedPoints: float, player1: array<string, mixed>, player2: array<string, mixed>}>
     */
    private function teamsFromResults(array $rows): array
    {
        $teams = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $eid = (int) ($row['EventParticipantId'] ?? 0);
            $standing = (int) ($row['Standing'] ?? 0);
            $rp = $row['RankingPoint'] ?? [];
            $pts = is_array($rp) ? (float) ($rp['Points'] ?? 0) : 0.0;
            $teams[] = [
                'eventParticipantId' => $eid,
                'standing' => $standing,
                'earnedPoints' => $pts,
                'player1' => [
                    'Player1ParticipantId' => (int) ($row['Player1ParticipantId'] ?? 0),
                    'Player1Name' => (string) ($row['Player1Name'] ?? ''),
                    'PlayerUrl' => (string) ($row['Player1Url'] ?? ''),
                ],
                'player2' => [
                    'Player2ParticipantId' => (int) ($row['Player2ParticipantId'] ?? 0),
                    'Player2Name' => (string) ($row['Player2Name'] ?? ''),
                    'PlayerUrl' => (string) ($row['Player2Url'] ?? ''),
                ],
            ];
        }

        return $teams;
    }

    /**
     * @return list<array{eventParticipantId: int, standing: int, earnedPoints: float, player1: array<string, mixed>, player2: array<string, mixed>}>
     */
    private function teamsFromPlayersForClass(array $json): array
    {
        $out = [];
        $participants = $json['Participants'] ?? [];
        if (! is_array($participants)) {
            return [];
        }
        foreach ($participants as $wrap) {
            if (! is_array($wrap)) {
                continue;
            }
            $p = $wrap['Participant'] ?? null;
            if (! is_array($p)) {
                continue;
            }
            $fp = $p['FirstPlayer'] ?? [];
            $sp = $p['SecondPlayer'] ?? [];
            if (! is_array($fp) || ! is_array($sp)) {
                continue;
            }
            $out[] = [
                'eventParticipantId' => (int) ($p['EventParticipantId'] ?? 0),
                'standing' => 0,
                'earnedPoints' => 0.0,
                'player1' => [
                    'Player1ParticipantId' => (int) ($fp['Id'] ?? 0),
                    'Player1Name' => (string) ($fp['Name'] ?? ''),
                    'PlayerUrl' => (string) ($fp['PlayerUrl'] ?? ''),
                    'RankedinId' => $this->playerRankedinKeyFromRow($fp),
                ],
                'player2' => [
                    'Player2ParticipantId' => (int) ($sp['Id'] ?? 0),
                    'Player2Name' => (string) ($sp['Name'] ?? ''),
                    'PlayerUrl' => (string) ($sp['PlayerUrl'] ?? ''),
                    'RankedinId' => $this->playerRankedinKeyFromRow($sp),
                ],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $drawDecoded
     * @return array<int, true>
     */
    private function parsePlayoffsParticipantIds(array $drawDecoded): array
    {
        $set = [];
        $root = isset($drawDecoded[0]) && is_array($drawDecoded[0]) ? $drawDecoded[0] : $drawDecoded;
        if (! is_array($root)) {
            return $set;
        }
        $elim = $root['Elimination'] ?? null;
        if (! is_array($elim)) {
            return $set;
        }
        $cells = $elim['FirstRoundParticipantCells'] ?? [];
        if (! is_array($cells)) {
            return $set;
        }
        foreach ($cells as $c) {
            if (is_array($c) && isset($c['EventParticipantId'])) {
                $set[(int) $c['EventParticipantId']] = true;
            }
        }

        return $set;
    }

    /**
     * @param  array<string, array<string, mixed>>  $playerByRankedinId
     */
    private function touchPlayer(array &$playerByRankedinId, string $rankedinId, string $name): void
    {
        if (! isset($playerByRankedinId[$rankedinId])) {
            $playerByRankedinId[$rankedinId] = [
                'rankedinId' => $rankedinId,
                'name' => $name,
                'bptTotalRankingPoints' => 0.0,
                'participations' => [],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $player
     */
    private function appendParticipation(
        array &$player,
        array $eventMeta,
        string $className,
        BptCanonicalDivision $canonical,
        int $teamsCount,
        float $earned,
        bool $gotPlayoffs,
        string $teamMateName,
        string $teamMatePlayerKey,
        int $tournamentId,
        int $classId,
    ): void {
        $player['participations'][] = [
            'eventId' => $eventMeta['eventId'] ?? $tournamentId,
            'eventName' => (string) ($eventMeta['eventName'] ?? ''),
            'startDate' => (string) ($eventMeta['startDate'] ?? ''),
            'endDate' => (string) ($eventMeta['endDate'] ?? ''),
            'eventDivision' => $className,
            'canonicalDivision' => $canonical->value,
            'tournamentClassId' => $classId,
            'totalTeams' => $teamsCount,
            'gotIntoPlayoffs' => $gotPlayoffs,
            'earnedPoints' => $earned,
            'teamMateName' => $teamMateName,
            'teamMatePlayerKey' => $teamMatePlayerKey,
        ];
    }

    /**
     * Full division standings (all players with ≥1 participation in the division), ordered like the public top list.
     *
     * @param  array<string, array<string, mixed>>  $playerByRankedinId
     * @return array<string, array<string, int>>  [ canonicalDivision value => [ rankedinId => 1-based rank ] ]
     */
    private function computeDivisionRankingNumbers(array $playerByRankedinId): array
    {
        $out = [];
        foreach (BptCanonicalDivision::cases() as $division) {
            $pool = [];
            foreach ($playerByRankedinId as $rid => $p) {
                $has = false;
                foreach ($p['participations'] ?? [] as $part) {
                    if (is_array($part) && ($part['canonicalDivision'] ?? '') === $division->value) {
                        $has = true;

                        break;
                    }
                }
                if (! $has) {
                    continue;
                }
                $pool[] = ['id' => $rid, 'p' => $p];
            }
            usort($pool, static function (array $a, array $b): int {
                $pa = (float) ($a['p']['bptTotalRankingPoints'] ?? 0);
                $pb = (float) ($b['p']['bptTotalRankingPoints'] ?? 0);
                if ($pa !== $pb) {
                    return $pb <=> $pa;
                }
                $na = (string) ($a['p']['name'] ?? '');
                $nb = (string) ($b['p']['name'] ?? '');
                if ($na !== $nb) {
                    return $na <=> $nb;
                }

                return strcmp((string) $a['id'], (string) $b['id']);
            });
            $rankById = [];
            $pos = 1;
            foreach ($pool as $item) {
                $rankById[(string) $item['id']] = $pos;
                ++$pos;
            }
            $out[$division->value] = $rankById;
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private function fetchAllRankingPoints(int $rankingId, int $rankingType, int $ageGroup, string $rankingDate): array
    {
        $pageSize = (int) $this->cfg['rankings_page_size'];
        $map = [];
        $skip = 0;
        $total = PHP_INT_MAX;
        while ($skip < $total) {
            $chunk = $this->client->getJson('v1/Ranking/GetRankingsAsync', [
                'rankingId' => $rankingId,
                'rankingType' => $rankingType,
                'ageGroup' => $ageGroup,
                'rankingDate' => $rankingDate,
                'weekFromNow' => 0,
                'language' => 'en',
                'skip' => $skip,
                'take' => $pageSize,
            ]);
            $payload = $chunk['Payload'] ?? [];
            $total = (int) ($chunk['TotalCount'] ?? 0);
            if (is_array($payload)) {
                foreach ($payload as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $rid = (string) ($row['RankedinId'] ?? '');
                    if ($rid === '') {
                        continue;
                    }
                    $pts = $row['ParticipantPoints'] ?? [];
                    $map[$rid] = is_array($pts) ? (float) ($pts['Points'] ?? 0) : 0.0;
                }
            }
            $skip += $pageSize;
            if (! is_array($payload) || $payload === [] || $skip >= $total) {
                break;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $allPlayers
     * @param  array<string, array<string, int>>  $divisionRanks
     */
    private function buildPlayerRow(
        string $playerKey,
        array $p,
        BptCanonicalDivision $forDivision,
        array $allPlayers,
        array $divisionRanks,
    ): array {
        $name = (string) ($p['name'] ?? '');
        $points = (float) ($p['bptTotalRankingPoints'] ?? 0.0);

        $eventsOccured = [];
        $seenEv = [];
        $divisionsParticipatedInto = [];
        $divisionValue = $forDivision->value;
        $ranksForDivision = $divisionRanks[$divisionValue] ?? [];

        foreach ($p['participations'] ?? [] as $part) {
            if (! is_array($part)) {
                continue;
            }
            if (($part['canonicalDivision'] ?? '') !== $divisionValue) {
                continue;
            }
            $eid = (string) ($part['eventId'] ?? '');
            if ($eid !== '' && ! isset($seenEv[$eid])) {
                $seenEv[$eid] = true;
                $eventsOccured[] = [
                    'eventId' => (int) $part['eventId'],
                    'eventName' => (string) ($part['eventName'] ?? ''),
                    'startDate' => (string) ($part['startDate'] ?? ''),
                    'endDate' => (string) ($part['endDate'] ?? ''),
                ];
            }
            $mateKey = (string) ($part['teamMatePlayerKey'] ?? '');
            $matePlayer = ($mateKey !== '' && isset($allPlayers[$mateKey])) ? $allPlayers[$mateKey] : null;
            $mateNameFallback = trim((string) ($part['teamMateName'] ?? ''));
            $mateName = $matePlayer !== null ? (string) ($matePlayer['name'] ?? '') : $mateNameFallback;
            $matePoints = $matePlayer !== null ? (float) ($matePlayer['bptTotalRankingPoints'] ?? 0.0) : 0.0;
            $mateRank = ($mateKey !== '' && isset($ranksForDivision[$mateKey])) ? $ranksForDivision[$mateKey] : null;

            $divisionsParticipatedInto[] = [
                'eventName' => (string) ($part['eventName'] ?? ''),
                'eventDivision' => (string) ($part['eventDivision'] ?? ''),
                'totalTeams' => (int) ($part['totalTeams'] ?? 0),
                'gotIntoPlayoffs' => (bool) ($part['gotIntoPlayoffs'] ?? false),
                'earnedPoints' => (float) ($part['earnedPoints'] ?? 0),
                'teamMate' => [
                    'rankedinId' => $mateKey,
                    'name' => $mateName,
                    'bptTotalRankingPoints' => $matePoints,
                    'divisionRankingNumber' => $mateRank,
                ],
            ];
        }

        return [
            'rankedinId' => $playerKey,
            'name' => $name,
            'bptTotalRankingPoints' => $points,
            'divisionRankingNumber' => 0,
            'eventsOccured' => $eventsOccured,
            'divisionsParticipatedInto' => $divisionsParticipatedInto,
        ];
    }

    private function playerKeyFromParticipantBlock(array $p): string
    {
        $url = $p['PlayerUrl'] ?? $p['Player1Url'] ?? $p['Player2Url'] ?? null;
        $fromUrl = self::playerKeyFromUrl(is_string($url) ? $url : null);
        if ($fromUrl !== null && $fromUrl !== '') {
            return $fromUrl;
        }

        return (string) ($p['RankedinId'] ?? '');
    }

    private function playerRankedinKeyFromRow(array $row): string
    {
        $r = (string) ($row['RankedinId'] ?? '');
        if ($r !== '') {
            return $r;
        }

        return (string) (self::playerKeyFromUrl((string) ($row['PlayerUrl'] ?? '')) ?? '');
    }

    public static function playerKeyFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (preg_match('#/player/([^/]+)/#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function rankedinIdFromPlayerUrl(?string $url): ?string
    {
        return self::playerKeyFromUrl($url);
    }
}

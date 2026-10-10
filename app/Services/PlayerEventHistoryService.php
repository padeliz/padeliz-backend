<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventDivisionStanding;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use Illuminate\Support\Collection;

final class PlayerEventHistoryService
{
    /**
     * @param  list<int>  $playerIds
     * @return array<int, array{
     *     last_played: null|array{division_id?: int, division_title: string, division_color: string, rank: null|int, participant_count: int},
     *     participated_count: int,
     *     banned_count: int,
     *     absented_count: int,
     *     played_recent: list<array{edition_name: string, division_title: string, division_color: string, rank: null|int, participant_count: int, date: string}>,
     *     participated_tooltip: array{lines: list<array{type: 'ellipsis'}|array{type: 'entry', edition_name: string, date: string}>},
     *     banned_recent: list<array{edition_name: string, date: string, ban_reason: string}>,
     *     absented_recent: list<array{edition_name: string, date: string, absence_reason: string}>
     * }>
     */
    public function forPlayers(array $playerIds, string $cutoffDateYmd): array
    {
        $playerIds = array_values(array_unique(array_map('intval', $playerIds)));
        $empty = $this->emptyHistory();

        $result = [];
        foreach ($playerIds as $playerId) {
            $result[$playerId] = $empty;
        }

        if ($playerIds === []) {
            return $result;
        }

        /** @var Collection<int, EventParticipation> $past */
        $past = EventParticipation::query()
            ->with(['event', 'eventDivision.division'])
            ->whereIn('player_id', $playerIds)
            ->whereHas('event', function ($q) use ($cutoffDateYmd) {
                $q->whereDate('date', '<', $cutoffDateYmd);
            })
            ->get();

        if ($past->isEmpty()) {
            return $result;
        }

        $participationIds = $past->pluck('id_participation')->all();
        $ranksByParticipationId = EventDivisionStanding::query()
            ->whereIn('participation_id', $participationIds)
            ->pluck('rank', 'participation_id');

        $eventDivisionIds = $past->pluck('event_division_id')->unique()->filter()->values()->all();
        $participantCounts = EventParticipation::query()
            ->whereIn('event_division_id', $eventDivisionIds)
            ->selectRaw('event_division_id, COUNT(*) as c')
            ->groupBy('event_division_id')
            ->pluck('c', 'event_division_id');

        $byPlayer = $past->groupBy('player_id');

        foreach ($byPlayer as $playerId => $rows) {
            $playerId = (int) $playerId;
            $participated = 0;
            $banned = 0;
            $absented = 0;
            /** @var list<EventParticipation> $cleanRows */
            $cleanRows = [];
            /** @var list<EventParticipation> $bannedRows */
            $bannedRows = [];
            /** @var list<EventParticipation> $absentedRows */
            $absentedRows = [];

            foreach ($rows as $row) {
                $isClean = $this->isClean($row);
                if ($isClean) {
                    $participated++;
                    $cleanRows[] = $row;
                }
                if ($this->isBanned($row)) {
                    $banned++;
                    $bannedRows[] = $row;
                }
                if ($row->absence_reason !== null) {
                    $absented++;
                    $absentedRows[] = $row;
                }
            }

            $cleanDesc = $this->sortParticipationsDesc($cleanRows);
            $lastPlayed = null;
            if ($cleanDesc !== []) {
                $latest = $cleanDesc[0];
                $edId = (int) $latest->event_division_id;
                $rank = $ranksByParticipationId->get($latest->id_participation);
                $divisionId = (int) ($latest->eventDivision?->division_id ?? 0);
                $lastPlayed = [
                    'division_title' => (string) ($latest->eventDivision?->division_title ?? ''),
                    'division_color' => $this->divisionColor($latest),
                    'rank' => $rank !== null ? (int) $rank : null,
                    'participant_count' => (int) ($participantCounts->get($edId) ?? 0),
                ];
                if ($divisionId > 0) {
                    $lastPlayed['division_id'] = $divisionId;
                }
            }

            $playedRecentNewestFirst = array_slice($cleanDesc, 0, 4);
            $playedRecent = array_reverse($playedRecentNewestFirst);
            $playedRecentPayload = [];
            foreach ($playedRecent as $row) {
                $edId = (int) $row->event_division_id;
                $rank = $ranksByParticipationId->get($row->id_participation);
                $playedRecentPayload[] = [
                    'edition_name' => $this->editionName($row),
                    'division_title' => (string) ($row->eventDivision?->division_title ?? ''),
                    'division_color' => $this->divisionColor($row),
                    'rank' => $rank !== null ? (int) $rank : null,
                    'participant_count' => (int) ($participantCounts->get($edId) ?? 0),
                    'date' => $this->editionDateYmd($row),
                ];
            }

            $cleanAsc = $this->sortParticipationsAsc($cleanRows);
            $bannedRecent = $this->recentWithReasons(
                $this->sortParticipationsDesc($bannedRows),
                static fn (EventParticipation $row): array => [
                    'ban_reason' => (string) $row->ban_reason,
                ]
            );
            $absentedRecent = $this->recentWithReasons(
                $this->sortParticipationsDesc($absentedRows),
                static fn (EventParticipation $row): array => [
                    'absence_reason' => (string) $row->absence_reason,
                ]
            );

            $result[$playerId] = [
                'last_played' => $lastPlayed,
                'participated_count' => $participated,
                'banned_count' => $banned,
                'absented_count' => $absented,
                'played_recent' => $playedRecentPayload,
                'participated_tooltip' => [
                    'lines' => $this->buildParticipatedTooltipLines($cleanAsc),
                ],
                'banned_recent' => $bannedRecent,
                'absented_recent' => $absentedRecent,
            ];
        }

        return $result;
    }

    public function cutoffDateForEdition(PadelEvent $event): string
    {
        if ($event->date instanceof \DateTimeInterface) {
            return $event->date->format('Y-m-d');
        }

        return (string) $event->formatted_date;
    }

    /**
     * @return array{
     *     last_played: null,
     *     participated_count: int,
     *     banned_count: int,
     *     absented_count: int,
     *     played_recent: list<empty>,
     *     participated_tooltip: array{lines: list<empty>},
     *     banned_recent: list<empty>,
     *     absented_recent: list<empty>
     * }
     */
    private function emptyHistory(): array
    {
        return [
            'last_played' => null,
            'participated_count' => 0,
            'banned_count' => 0,
            'absented_count' => 0,
            'played_recent' => [],
            'participated_tooltip' => ['lines' => []],
            'banned_recent' => [],
            'absented_recent' => [],
        ];
    }

    /**
     * @param  list<EventParticipation>  $rows
     * @return list<array{type: 'ellipsis'}|array{type: 'entry', edition_name: string, date: string}>
     */
    private function buildParticipatedTooltipLines(array $rowsAsc): array
    {
        $count = count($rowsAsc);
        if ($count === 0) {
            return [];
        }

        if ($count <= 6) {
            return array_map(fn (EventParticipation $row): array => $this->participatedEntry($row), $rowsAsc);
        }

        $oldest = array_slice($rowsAsc, 0, 3);
        $newest = array_slice($rowsAsc, -3);
        $lines = array_map(fn (EventParticipation $row): array => $this->participatedEntry($row), $oldest);
        $lines[] = ['type' => 'ellipsis'];

        return array_merge(
            $lines,
            array_map(fn (EventParticipation $row): array => $this->participatedEntry($row), $newest)
        );
    }

    /**
     * @return array{type: 'entry', edition_name: string, date: string}
     */
    private function participatedEntry(EventParticipation $row): array
    {
        return [
            'type' => 'entry',
            'edition_name' => $this->editionName($row),
            'date' => $this->editionDateYmd($row),
        ];
    }

    /**
     * @param  list<EventParticipation>  $rowsDesc
     * @param  callable(EventParticipation): array<string, string>  $extra
     * @return list<array{edition_name: string, date: string, ban_reason?: string, absence_reason?: string}>
     */
    private function recentWithReasons(array $rowsDesc, callable $extra): array
    {
        $newest = array_slice($rowsDesc, 0, 4);
        $asc = array_reverse($newest);
        $out = [];
        foreach ($asc as $row) {
            $out[] = array_merge([
                'edition_name' => $this->editionName($row),
                'date' => $this->editionDateYmd($row),
            ], $extra($row));
        }

        return $out;
    }

    /**
     * @param  list<EventParticipation>  $rows
     * @return list<EventParticipation>
     */
    private function sortParticipationsDesc(array $rows): array
    {
        usort($rows, function (EventParticipation $a, EventParticipation $b): int {
            $dateA = $this->editionDateYmd($a);
            $dateB = $this->editionDateYmd($b);
            if ($dateA !== $dateB) {
                return strcmp($dateB, $dateA);
            }

            return ((int) $b->id_participation) <=> ((int) $a->id_participation);
        });

        return $rows;
    }

    /**
     * @param  list<EventParticipation>  $rows
     * @return list<EventParticipation>
     */
    private function sortParticipationsAsc(array $rows): array
    {
        usort($rows, function (EventParticipation $a, EventParticipation $b): int {
            $dateA = $this->editionDateYmd($a);
            $dateB = $this->editionDateYmd($b);
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }

            return ((int) $a->id_participation) <=> ((int) $b->id_participation);
        });

        return $rows;
    }

    public function isClean(EventParticipation $row): bool
    {
        if ($row->absence_reason !== null) {
            return false;
        }

        $ban = $row->ban_reason;

        return $ban === null || $ban === '';
    }

    private function isBanned(EventParticipation $row): bool
    {
        $ban = $row->ban_reason;

        return $ban !== null && $ban !== '';
    }

    private function editionDateYmd(EventParticipation $row): string
    {
        $event = $row->event;
        if ($event === null) {
            return '';
        }
        if ($event->date instanceof \DateTimeInterface) {
            return $event->date->format('Y-m-d');
        }

        return (string) ($event->formatted_date ?? '');
    }

    private function editionName(EventParticipation $row): string
    {
        return (string) ($row->event?->name ?? '');
    }

    private function divisionColor(EventParticipation $row): string
    {
        $color = $row->eventDivision?->division?->color;

        return is_string($color) ? $color : '';
    }
}

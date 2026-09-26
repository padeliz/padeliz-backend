<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Edition;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use Illuminate\Support\Collection;

final class PlayerEditionHistoryService
{
    /**
     * @param  list<int>  $playerIds
     * @return array<int, array{
     *     last_played: null|array{division_id?: int, division_title: string, rank: null|int, participant_count: int},
     *     participated_count: int,
     *     banned_count: int,
     *     absented_count: int,
     *     played_recent: list<array{edition_name: string, division_title: string, rank: null|int, participant_count: int, date: string}>,
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

        /** @var Collection<int, EditionParticipation> $past */
        $past = EditionParticipation::query()
            ->with(['edition', 'editionDivision'])
            ->whereIn('player_id', $playerIds)
            ->whereHas('edition', function ($q) use ($cutoffDateYmd) {
                $q->whereDate('date', '<', $cutoffDateYmd);
            })
            ->get();

        if ($past->isEmpty()) {
            return $result;
        }

        $participationIds = $past->pluck('id_participation')->all();
        $ranksByParticipationId = EditionDivisionStanding::query()
            ->whereIn('participation_id', $participationIds)
            ->pluck('rank', 'participation_id');

        $editionDivisionIds = $past->pluck('edition_division_id')->unique()->filter()->values()->all();
        $participantCounts = EditionParticipation::query()
            ->whereIn('edition_division_id', $editionDivisionIds)
            ->selectRaw('edition_division_id, COUNT(*) as c')
            ->groupBy('edition_division_id')
            ->pluck('c', 'edition_division_id');

        $byPlayer = $past->groupBy('player_id');

        foreach ($byPlayer as $playerId => $rows) {
            $playerId = (int) $playerId;
            $participated = 0;
            $banned = 0;
            $absented = 0;
            /** @var list<EditionParticipation> $cleanRows */
            $cleanRows = [];
            /** @var list<EditionParticipation> $bannedRows */
            $bannedRows = [];
            /** @var list<EditionParticipation> $absentedRows */
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
                $edId = (int) $latest->edition_division_id;
                $rank = $ranksByParticipationId->get($latest->id_participation);
                $divisionId = (int) ($latest->editionDivision?->division_id ?? 0);
                $lastPlayed = [
                    'division_title' => (string) ($latest->editionDivision?->division_title ?? ''),
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
                $edId = (int) $row->edition_division_id;
                $rank = $ranksByParticipationId->get($row->id_participation);
                $playedRecentPayload[] = [
                    'edition_name' => $this->editionName($row),
                    'division_title' => (string) ($row->editionDivision?->division_title ?? ''),
                    'rank' => $rank !== null ? (int) $rank : null,
                    'participant_count' => (int) ($participantCounts->get($edId) ?? 0),
                    'date' => $this->editionDateYmd($row),
                ];
            }

            $cleanAsc = $this->sortParticipationsAsc($cleanRows);
            $bannedRecent = $this->recentWithReasons(
                $this->sortParticipationsDesc($bannedRows),
                static fn (EditionParticipation $row): array => [
                    'ban_reason' => (string) $row->ban_reason,
                ]
            );
            $absentedRecent = $this->recentWithReasons(
                $this->sortParticipationsDesc($absentedRows),
                static fn (EditionParticipation $row): array => [
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

    public function cutoffDateForEdition(Edition $edition): string
    {
        if ($edition->date instanceof \DateTimeInterface) {
            return $edition->date->format('Y-m-d');
        }

        return (string) $edition->formatted_date;
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
     * @param  list<EditionParticipation>  $rows
     * @return list<array{type: 'ellipsis'}|array{type: 'entry', edition_name: string, date: string}>
     */
    private function buildParticipatedTooltipLines(array $rowsAsc): array
    {
        $count = count($rowsAsc);
        if ($count === 0) {
            return [];
        }

        if ($count <= 6) {
            return array_map(fn (EditionParticipation $row): array => $this->participatedEntry($row), $rowsAsc);
        }

        $oldest = array_slice($rowsAsc, 0, 3);
        $newest = array_slice($rowsAsc, -3);
        $lines = array_map(fn (EditionParticipation $row): array => $this->participatedEntry($row), $oldest);
        $lines[] = ['type' => 'ellipsis'];

        return array_merge(
            $lines,
            array_map(fn (EditionParticipation $row): array => $this->participatedEntry($row), $newest)
        );
    }

    /**
     * @return array{type: 'entry', edition_name: string, date: string}
     */
    private function participatedEntry(EditionParticipation $row): array
    {
        return [
            'type' => 'entry',
            'edition_name' => $this->editionName($row),
            'date' => $this->editionDateYmd($row),
        ];
    }

    /**
     * @param  list<EditionParticipation>  $rowsDesc
     * @param  callable(EditionParticipation): array<string, string>  $extra
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
     * @param  list<EditionParticipation>  $rows
     * @return list<EditionParticipation>
     */
    private function sortParticipationsDesc(array $rows): array
    {
        usort($rows, function (EditionParticipation $a, EditionParticipation $b): int {
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
     * @param  list<EditionParticipation>  $rows
     * @return list<EditionParticipation>
     */
    private function sortParticipationsAsc(array $rows): array
    {
        usort($rows, function (EditionParticipation $a, EditionParticipation $b): int {
            $dateA = $this->editionDateYmd($a);
            $dateB = $this->editionDateYmd($b);
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }

            return ((int) $a->id_participation) <=> ((int) $b->id_participation);
        });

        return $rows;
    }

    private function isClean(EditionParticipation $row): bool
    {
        if ($row->absence_reason !== null) {
            return false;
        }

        $ban = $row->ban_reason;

        return $ban === null || $ban === '';
    }

    private function isBanned(EditionParticipation $row): bool
    {
        $ban = $row->ban_reason;

        return $ban !== null && $ban !== '';
    }

    private function editionDateYmd(EditionParticipation $row): string
    {
        $edition = $row->edition;
        if ($edition === null) {
            return '';
        }
        if ($edition->date instanceof \DateTimeInterface) {
            return $edition->date->format('Y-m-d');
        }

        return (string) ($edition->formatted_date ?? '');
    }

    private function editionName(EditionParticipation $row): string
    {
        return (string) ($row->edition?->name ?? '');
    }
}

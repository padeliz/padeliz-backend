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
     *     last_played: null|array{division_title: string, rank: null|int, participant_count: int},
     *     participated_count: int,
     *     banned_count: int,
     *     absented_count: int
     * }>
     */
    public function forPlayers(array $playerIds, string $cutoffDateYmd): array
    {
        $playerIds = array_values(array_unique(array_map('intval', $playerIds)));
        $empty = [
            'last_played' => null,
            'participated_count' => 0,
            'banned_count' => 0,
            'absented_count' => 0,
        ];

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
            $cleanRows = [];

            foreach ($rows as $row) {
                $isClean = $this->isClean($row);
                if ($isClean) {
                    $participated++;
                    $cleanRows[] = $row;
                }
                if ($this->isBanned($row)) {
                    $banned++;
                }
                if ($row->absence_reason !== null) {
                    $absented++;
                }
            }

            $lastPlayed = null;
            if ($cleanRows !== []) {
                usort($cleanRows, function (EditionParticipation $a, EditionParticipation $b): int {
                    $dateA = $this->editionDateYmd($a);
                    $dateB = $this->editionDateYmd($b);
                    if ($dateA !== $dateB) {
                        return strcmp($dateB, $dateA);
                    }

                    return ((int) $b->id_participation) <=> ((int) $a->id_participation);
                });
                $latest = $cleanRows[0];
                $edId = (int) $latest->edition_division_id;
                $rank = $ranksByParticipationId->get($latest->id_participation);
                $lastPlayed = [
                    'division_title' => (string) ($latest->editionDivision?->division_title ?? ''),
                    'rank' => $rank !== null ? (int) $rank : null,
                    'participant_count' => (int) ($participantCounts->get($edId) ?? 0),
                ];
            }

            $result[$playerId] = [
                'last_played' => $lastPlayed,
                'participated_count' => $participated,
                'banned_count' => $banned,
                'absented_count' => $absented,
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
}

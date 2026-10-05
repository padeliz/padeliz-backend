<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\CachedPdfPlayerImage;
use App\Models\EventParticipation;
use Illuminate\Support\Collection;

/**
 * Attaches pdf_avatar_url to participation/player payloads without baking into read caches.
 */
final class CachedPdfAvatarAttacher
{
    /**
     * Overlay pdf_avatar_url on schedule/index participation array rows.
     *
     * @param  array<int, array<string, mixed>>|list<array<string, mixed>>  $participations
     */
    public function overlayParticipationsOnArray(array &$participations): void
    {
        $pairs = [];
        foreach ($participations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $playerId = (int) ($row['player_id'] ?? 0);
            if ($playerId < 1) {
                continue;
            }
            $collecting = (bool) ($row['is_collecting_points'] ?? false);
            $pairs[$playerId . ':' . ($collecting ? '1' : '0')] = [
                'player_id' => $playerId,
                'collecting' => $collecting,
            ];
        }

        $urls = CachedPdfPlayerImage::urlsByPlayerCollectingPairs(array_values($pairs));

        foreach ($participations as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $playerId = (int) ($row['player_id'] ?? 0);
            if ($playerId < 1) {
                $row['pdf_avatar_url'] = null;

                continue;
            }
            $collecting = (bool) ($row['is_collecting_points'] ?? false);
            $key = $playerId . ':' . ($collecting ? '1' : '0');
            $row['pdf_avatar_url'] = $urls[$key] ?? null;
        }
        unset($row);
    }

    /**
     * @param  Collection<int, EventParticipation>|iterable<EventParticipation>  $participations
     */
    public function hydrateParticipations(iterable $participations): void
    {
        $items = $participations instanceof Collection
            ? $participations
            : Collection::make($participations);

        if ($items->isEmpty()) {
            return;
        }

        $pairs = [];
        foreach ($items as $participation) {
            if (! $participation instanceof EventParticipation) {
                continue;
            }
            $playerId = (int) $participation->player_id;
            if ($playerId < 1) {
                continue;
            }
            $collecting = (bool) $participation->is_collecting_points;
            $pairs[$playerId . ':' . ($collecting ? '1' : '0')] = [
                'player_id' => $playerId,
                'collecting' => $collecting,
            ];
        }

        $urls = CachedPdfPlayerImage::urlsByPlayerCollectingPairs(array_values($pairs));

        foreach ($items as $participation) {
            if (! $participation instanceof EventParticipation) {
                continue;
            }
            $playerId = (int) $participation->player_id;
            if ($playerId < 1) {
                $participation->setAttribute('pdf_avatar_url', null);

                continue;
            }
            $collecting = (bool) $participation->is_collecting_points;
            $key = $playerId . ':' . ($collecting ? '1' : '0');
            $participation->setAttribute('pdf_avatar_url', $urls[$key] ?? null);
        }
    }

    public function hydrateParticipation(EventParticipation $participation): void
    {
        $this->hydrateParticipations([$participation]);
    }

    /**
     * Overlay pdf_avatar_url on player index rows (prefer collecting=true).
     *
     * @param  array<int, array<string, mixed>>|list<array<string, mixed>>  $players
     */
    public function overlayPlayersOnArray(array &$players): void
    {
        $playerIds = [];
        foreach ($players as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id_player'] ?? 0);
            if ($id > 0) {
                $playerIds[$id] = $id;
            }
        }

        if ($playerIds === []) {
            return;
        }

        $urls = $this->urlsPreferCollecting(array_values($playerIds));

        foreach ($players as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id_player'] ?? 0);
            $row['pdf_avatar_url'] = $id > 0 ? ($urls[$id] ?? null) : null;
        }
        unset($row);
    }

    /**
     * Overlay pdf_avatar_url on a single player show payload.
     *
     * @param  array<string, mixed>  $player
     */
    public function overlayPlayerOnArray(array &$player): void
    {
        $id = (int) ($player['id_player'] ?? 0);
        $player['pdf_avatar_url'] = $id > 0
            ? CachedPdfPlayerImage::urlForPlayerPreferCollecting($id)
            : null;
    }

    /**
     * @param  list<int>  $playerIds
     * @return array<int, null|string>
     */
    private function urlsPreferCollecting(array $playerIds): array
    {
        $pairs = [];
        foreach ($playerIds as $playerId) {
            $pairs[] = ['player_id' => $playerId, 'collecting' => true];
            $pairs[] = ['player_id' => $playerId, 'collecting' => false];
        }

        $byKey = CachedPdfPlayerImage::urlsByPlayerCollectingPairs($pairs);
        $result = [];
        foreach ($playerIds as $playerId) {
            $result[$playerId] = $byKey[$playerId . ':1'] ?? $byKey[$playerId . ':0'] ?? null;
        }

        return $result;
    }
}

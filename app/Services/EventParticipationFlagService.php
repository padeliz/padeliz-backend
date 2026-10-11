<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventParticipation;
use App\Models\EventRegistration;
use Illuminate\Support\Collection;

final class EventParticipationFlagService
{
    public function __construct(
        private readonly EventParticipationLotteryGuard $lotteryGuard,
    ) {
    }

    /**
     * @param  Collection<int, EventParticipation>  $participations
     * @return array{
     *     flags_by_participation_id: array<int, list<string>>,
     *     event_summary: array{
     *         registered_not_participating_count: int,
     *         participations_locked: bool
     *     }
     * }
     */
    public function forEventParticipations(int $editionId, Collection $participations): array
    {
        if ($editionId <= 0) {
            return [
                'flags_by_participation_id' => [],
                'event_summary' => [
                    'registered_not_participating_count' => 0,
                    'participations_locked' => false,
                ],
            ];
        }

        $registeredPlayerIds = EventRegistration::query()
            ->where('event_id', $editionId)
            ->pluck('player_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $participatingPlayerIds = EventParticipation::query()
            ->where('event_id', $editionId)
            ->pluck('player_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $registeredSet = array_fill_keys($registeredPlayerIds, true);
        $participatingSet = array_fill_keys($participatingPlayerIds, true);

        $notParticipatingCount = 0;
        foreach ($registeredPlayerIds as $playerId) {
            if (! isset($participatingSet[$playerId])) {
                $notParticipatingCount++;
            }
        }

        $countsByPlayerId = EventParticipation::query()
            ->where('event_id', $editionId)
            ->selectRaw('player_user_id, COUNT(*) as c')
            ->groupBy('player_user_id')
            ->pluck('c', 'player_user_id');

        $flagsByParticipationId = [];
        foreach ($participations as $participation) {
            $flags = [];
            $playerId = (int) $participation->player_user_id;
            if (((int) ($countsByPlayerId[$playerId] ?? 0)) > 1) {
                $flags[] = 'duplicate';
            }
            if (! isset($registeredSet[$playerId])) {
                $flags[] = 'not_registered';
            }
            $flagsByParticipationId[(int) $participation->id_participation] = $flags;
        }

        return [
            'flags_by_participation_id' => $flagsByParticipationId,
            'event_summary' => [
                'registered_not_participating_count' => $notParticipatingCount,
                'participations_locked' => $this->lotteryGuard->hasLotteryLuckiesForEdition($editionId),
            ],
        ];
    }
}

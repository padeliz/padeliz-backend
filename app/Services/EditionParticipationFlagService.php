<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EditionParticipation;
use App\Models\EditionRegistration;
use Illuminate\Support\Collection;

final class EditionParticipationFlagService
{
    public function __construct(
        private readonly EditionParticipationLotteryGuard $lotteryGuard,
    ) {
    }

    /**
     * @param  Collection<int, EditionParticipation>  $participations
     * @return array{
     *     flags_by_participation_id: array<int, list<string>>,
     *     edition_summary: array{
     *         registered_not_participating_count: int,
     *         participations_locked: bool
     *     }
     * }
     */
    public function forEditionParticipations(int $editionId, Collection $participations): array
    {
        if ($editionId <= 0) {
            return [
                'flags_by_participation_id' => [],
                'edition_summary' => [
                    'registered_not_participating_count' => 0,
                    'participations_locked' => false,
                ],
            ];
        }

        $registeredPlayerIds = EditionRegistration::query()
            ->where('edition_id', $editionId)
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $participatingPlayerIds = EditionParticipation::query()
            ->where('edition_id', $editionId)
            ->pluck('player_id')
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

        $countsByPlayerId = EditionParticipation::query()
            ->where('edition_id', $editionId)
            ->selectRaw('player_id, COUNT(*) as c')
            ->groupBy('player_id')
            ->pluck('c', 'player_id');

        $flagsByParticipationId = [];
        foreach ($participations as $participation) {
            $flags = [];
            $playerId = (int) $participation->player_id;
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
            'edition_summary' => [
                'registered_not_participating_count' => $notParticipatingCount,
                'participations_locked' => $this->lotteryGuard->hasLotteryLuckiesForEdition($editionId),
            ],
        ];
    }
}

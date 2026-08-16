<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Services\Pdf\EditionDivisionPosterInvalidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Persists Mode A schedule form fields on an EditionDivision.
 */
final class EditionDivisionScheduleFormService
{
    /** @var list<string> */
    private const PDF_RELEVANT_ED_FIELDS = [
        'include_final',
        'has_demonstrative_match',
        'include_scores',
        'allow_replacements',
        'points_per_match',
        'final_points_target',
        'playing_start_time',
        'playing_end_time',
    ];

    public function __construct(
        private readonly EditionDivisionPosterInvalidator $posterInvalidator = new EditionDivisionPosterInvalidator(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function persist(EditionDivision $editionDivision, array $data): EditionDivision
    {
        return DB::transaction(function () use ($editionDivision, $data): EditionDivision {
            $shouldInvalidate = $this->detectPdfRelevantChanges($editionDivision, $data);

            $edFields = array_intersect_key($data, array_flip([
                'playing_start_time',
                'playing_end_time',
                'player_opponents_nr',
                'player_matches_repeat',
                'include_final',
                'has_demonstrative_match',
                'include_scores',
                'allow_replacements',
                'points_per_match',
                'final_points_target',
            ]));

            if ($edFields !== []) {
                if (isset($edFields['playing_start_time'])) {
                    $edFields['playing_start_time'] = PlayingWindow::normalizeStoredTime((string) $edFields['playing_start_time']);
                }
                if (isset($edFields['playing_end_time'])) {
                    $edFields['playing_end_time'] = PlayingWindow::normalizeStoredTime((string) $edFields['playing_end_time']);
                }
                $editionDivision->update($edFields);
            }

            if (array_key_exists('court_ids', $data)) {
                $this->syncCourts($editionDivision, array_values(array_map('intval', $data['court_ids'] ?? [])));
            }

            if (array_key_exists('participation_ids', $data)) {
                $this->syncParticipationOrder(
                    $editionDivision,
                    array_values(array_map('intval', $data['participation_ids'] ?? [])),
                    $data['is_collecting_points'] ?? null,
                );
            } elseif (array_key_exists('is_collecting_points', $data) && is_array($data['is_collecting_points'])) {
                $this->syncCollectingPointsOnly($editionDivision, $data['is_collecting_points']);
            }

            if ($shouldInvalidate) {
                $this->posterInvalidator->clearMatchesAndRanking($editionDivision);
            }

            return $editionDivision->fresh([
                'courts',
                'editionDivisionCourts.court',
                'participations.player',
            ]) ?? $editionDivision;
        });
    }

    /**
     * @param  list<int>  $courtIds
     */
    public function syncCourts(EditionDivision $editionDivision, array $courtIds): void
    {
        $sync = [];
        foreach ($courtIds as $index => $courtId) {
            $sync[$courtId] = [
                'display_order' => $index,
            ];
        }

        $editionDivision->courts()->sync($sync);
    }

    /**
     * @param  list<int>  $participationIds
     * @param  null|array<int|string, bool>  $collectingPoints
     */
    public function syncParticipationOrder(
        EditionDivision $editionDivision,
        array $participationIds,
        ?array $collectingPoints = null,
    ): void {
        $existingIds = EditionParticipation::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->pluck('id_participation')
            ->map(static fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $requestedSorted = $participationIds;
        sort($requestedSorted);

        if ($requestedSorted !== $existingIds) {
            throw new InvalidArgumentException(
                'participation_ids must include exactly the existing participations for this edition division (no add/remove).'
            );
        }

        foreach ($participationIds as $index => $participationId) {
            $updates = ['sort_order' => $index];
            if (is_array($collectingPoints) && array_key_exists($participationId, $collectingPoints)) {
                $updates['is_collecting_points'] = (bool) $collectingPoints[$participationId];
            } elseif (is_array($collectingPoints) && array_key_exists((string) $participationId, $collectingPoints)) {
                $updates['is_collecting_points'] = (bool) $collectingPoints[(string) $participationId];
            }

            EditionParticipation::query()
                ->where('id_participation', $participationId)
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function detectPdfRelevantChanges(EditionDivision $editionDivision, array $data): bool
    {
        foreach (self::PDF_RELEVANT_ED_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $incoming = $data[$field];
            if (in_array($field, ['playing_start_time', 'playing_end_time'], true) && $incoming !== null) {
                $incoming = PlayingWindow::normalizeStoredTime((string) $incoming);
            }
            if ($field === 'include_final' || $field === 'has_demonstrative_match'
                || $field === 'include_scores' || $field === 'allow_replacements') {
                if ((bool) $incoming !== (bool) $editionDivision->{$field}) {
                    return true;
                }

                continue;
            }
            if ((string) $incoming !== (string) $editionDivision->{$field}) {
                return true;
            }
        }

        if (array_key_exists('court_ids', $data)) {
            $current = $editionDivision->courts()
                ->orderByPivot('display_order')
                ->pluck('courts.id_court')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();
            $incoming = array_values(array_map('intval', $data['court_ids'] ?? []));
            if ($current !== $incoming) {
                return true;
            }
        }

        if (array_key_exists('is_collecting_points', $data) && is_array($data['is_collecting_points'])) {
            if ($this->collectingMapChanged($editionDivision, $data['is_collecting_points'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, bool>  $collectingPoints
     */
    private function collectingMapChanged(EditionDivision $editionDivision, array $collectingPoints): bool
    {
        $rows = EditionParticipation::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->get(['id_participation', 'is_collecting_points']);

        foreach ($rows as $row) {
            $id = (int) $row->id_participation;
            if (array_key_exists($id, $collectingPoints)) {
                if ((bool) $collectingPoints[$id] !== (bool) $row->is_collecting_points) {
                    return true;
                }
            } elseif (array_key_exists((string) $id, $collectingPoints)) {
                if ((bool) $collectingPoints[(string) $id] !== (bool) $row->is_collecting_points) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, bool>  $collectingPoints
     */
    private function syncCollectingPointsOnly(EditionDivision $editionDivision, array $collectingPoints): void
    {
        foreach ($collectingPoints as $participationId => $value) {
            EditionParticipation::query()
                ->where('id_participation', (int) $participationId)
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->update(['is_collecting_points' => (bool) $value]);
        }
    }
}

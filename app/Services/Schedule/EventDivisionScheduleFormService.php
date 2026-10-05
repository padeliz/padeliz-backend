<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\EventDivision;
use App\Models\EventDivisionSchedule;
use App\Models\EventParticipation;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Persists Mode A schedule form fields on the edition division schedule row.
 *
 * Playing times stay on the edition division and are never written here.
 */
final class EventDivisionScheduleFormService
{
    /** @var list<string> */
    private const SCHEDULE_FIELDS = [
        'player_opponents_nr',
        'player_matches_repeat',
        'include_final',
        'has_demonstrative_match',
        'include_scores',
        'allow_replacements',
        'points_per_match',
        'final_points_target',
        'adjust',
    ];

    /** @var list<string> */
    private const PDF_RELEVANT_FIELDS = self::SCHEDULE_FIELDS;

    /** @var list<string> */
    private const BOOLEAN_FIELDS = [
        'include_final',
        'has_demonstrative_match',
        'include_scores',
        'allow_replacements',
    ];

    public function __construct(
        private readonly EventDivisionPosterInvalidator $posterInvalidator = new EventDivisionPosterInvalidator(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function persist(EventDivision $eventDivision, array $data): EventDivision
    {
        return DB::transaction(function () use ($eventDivision, $data): EventDivision {
            $schedule = $this->firstOrCreateSchedule($eventDivision);
            $shouldInvalidate = $this->detectPdfRelevantChanges($eventDivision, $schedule, $data);

            $scheduleFields = array_intersect_key($data, array_flip(self::SCHEDULE_FIELDS));
            if (array_key_exists('adjust', $scheduleFields)) {
                $scheduleFields['adjust'] = $this->normalizeAdjust($scheduleFields['adjust']);
            }

            if ($scheduleFields !== []) {
                $schedule->update($scheduleFields);
            }

            if (array_key_exists('court_ids', $data)) {
                $this->syncCourts($eventDivision, array_values(array_map('intval', $data['court_ids'] ?? [])));
            }

            if (array_key_exists('participation_ids', $data)) {
                $this->syncParticipationOrder(
                    $eventDivision,
                    array_values(array_map('intval', $data['participation_ids'] ?? [])),
                    $data['is_collecting_points'] ?? null,
                );
            } elseif (array_key_exists('is_collecting_points', $data) && is_array($data['is_collecting_points'])) {
                $this->syncCollectingPointsOnly($eventDivision, $data['is_collecting_points']);
            }

            if ($shouldInvalidate) {
                $this->posterInvalidator->clearMatchesAndRanking($eventDivision);
            }

            return $eventDivision->fresh([
                'schedule',
                'courts',
                'eventDivisionCourts.court',
                'participations.player',
            ]) ?? $eventDivision;
        });
    }

    public function firstOrCreateSchedule(EventDivision $eventDivision): EventDivisionSchedule
    {
        $schedule = EventDivisionSchedule::query()->firstOrNew([
            'event_division_id' => $eventDivision->id_event_division,
        ]);

        if (! $schedule->exists) {
            $schedule->save();
            // Column defaults (include_scores, final_points_target, adjust, …) only exist in the DB.
            $schedule->refresh();
        }

        return $schedule;
    }

    /**
     * @param  list<int>  $courtIds
     */
    public function syncCourts(EventDivision $eventDivision, array $courtIds): void
    {
        $sync = [];
        foreach ($courtIds as $index => $courtId) {
            $sync[$courtId] = [
                'display_order' => $index,
            ];
        }

        $eventDivision->courts()->sync($sync);
    }

    /**
     * @param  list<int>  $participationIds
     * @param  null|array<int|string, bool>  $collectingPoints
     */
    public function syncParticipationOrder(
        EventDivision $eventDivision,
        array $participationIds,
        ?array $collectingPoints = null,
    ): void {
        $existingIds = EventParticipation::query()
            ->where('event_division_id', $eventDivision->id_event_division)
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

            EventParticipation::query()
                ->where('id_participation', $participationId)
                ->where('event_division_id', $eventDivision->id_event_division)
                ->update($updates);
        }
    }

    private function normalizeAdjust(mixed $value): int
    {
        $adjust = (int) $value;
        if (! in_array($adjust, EventDivisionSchedule::ADJUST_VALUES, true)) {
            throw new InvalidArgumentException(sprintf(
                'adjust must be one of %s.',
                implode(', ', EventDivisionSchedule::ADJUST_VALUES)
            ));
        }

        return $adjust;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function detectPdfRelevantChanges(
        EventDivision $eventDivision,
        EventDivisionSchedule $schedule,
        array $data,
    ): bool {
        foreach (self::PDF_RELEVANT_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $incoming = $data[$field];
            if (in_array($field, self::BOOLEAN_FIELDS, true)) {
                if ((bool) $incoming !== (bool) $schedule->{$field}) {
                    return true;
                }

                continue;
            }
            if ((string) $incoming !== (string) $schedule->{$field}) {
                return true;
            }
        }

        if (array_key_exists('court_ids', $data)) {
            $current = $eventDivision->courts()
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
            if ($this->collectingMapChanged($eventDivision, $data['is_collecting_points'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, bool>  $collectingPoints
     */
    private function collectingMapChanged(EventDivision $eventDivision, array $collectingPoints): bool
    {
        $rows = EventParticipation::query()
            ->where('event_division_id', $eventDivision->id_event_division)
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
    private function syncCollectingPointsOnly(EventDivision $eventDivision, array $collectingPoints): void
    {
        foreach ($collectingPoints as $participationId => $value) {
            EventParticipation::query()
                ->where('id_participation', (int) $participationId)
                ->where('event_division_id', $eventDivision->id_event_division)
                ->update(['is_collecting_points' => (bool) $value]);
        }
    }
}

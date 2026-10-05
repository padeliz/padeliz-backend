<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EventDivision;
use App\Models\EventDivisionPoster;
use Illuminate\Support\Facades\Storage;

/**
 * Clears generated poster files while keeping printed_path intact.
 */
final class EventDivisionPosterInvalidator
{
    public function __construct(
        private readonly EventPosterInvalidationGuard $guard = new EventPosterInvalidationGuard(),
    ) {
    }

    /**
     * @param  list<string>  $kinds
     */
    public function clearGenerated(EventDivision $eventDivision, array $kinds): int
    {
        if ($kinds === []) {
            return 0;
        }

        $eventDivision->loadMissing('event');
        $editionId = (int) ($eventDivision->event_id ?? $eventDivision->event?->id_event ?? 0);
        if ($editionId <= 0 || ! $this->guard->shouldInvalidateEditionId($editionId)) {
            return 0;
        }

        $posters = EventDivisionPoster::query()
            ->where('event_division_id', $eventDivision->id_event_division)
            ->whereIn('poster_kind', $kinds)
            ->whereNotNull('generated_path')
            ->get();

        return $this->clearPosters($posters);
    }

    public function clearMatches(EventDivision $eventDivision): int
    {
        return $this->clearGenerated($eventDivision, ['MATCHES']);
    }

    public function clearMatchesAndRanking(EventDivision $eventDivision): int
    {
        return $this->clearGenerated($eventDivision, ['MATCHES', 'RANKING']);
    }

    public function clearAttendanceForEvent(int $editionId): int
    {
        if (! $this->guard->shouldInvalidateEditionId($editionId)) {
            return 0;
        }

        $posters = EventDivisionPoster::query()
            ->where('event_id', $editionId)
            ->whereNull('event_division_id')
            ->where('poster_kind', 'ATTENDANCE')
            ->whereNotNull('generated_path')
            ->get();

        return $this->clearPosters($posters);
    }

    /**
     * @param  iterable<EventDivisionPoster>  $posters
     */
    private function clearPosters(iterable $posters): int
    {
        $cleared = 0;
        foreach ($posters as $poster) {
            $path = $poster->generated_path;
            if ($path !== null && $path !== '') {
                Storage::disk($poster->storageDisk())->delete($path);
            }
            $poster->generated_path = null;
            $poster->save();
            $cleared++;
        }

        return $cleared;
    }
}

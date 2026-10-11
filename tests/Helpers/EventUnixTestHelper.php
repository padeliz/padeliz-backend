<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\EventCoverImage;
use App\Models\PadelEvent;
use App\Services\EventDeadlineTimestamps;
use Carbon\Carbon;
use Tests\Support\UnixEventFactory;

trait EventUnixTestHelper
{
    /**
     * @return array{event_starts_at: int, event_ends_at: int}
     */
    protected function eventWindow(string $ymd, string $start = '09:00:00', string $end = '18:00:00'): array
    {
        $bounds = UnixEventFactory::eventBounds($ymd, $start, $end);

        return [
            'event_starts_at' => $bounds['event_starts_at'],
            'event_ends_at' => $bounds['event_ends_at'],
        ];
    }

    /**
     * @return array{playing_starts_at: int, playing_ends_at: int}
     */
    protected function playingWindow(string $ymd, string $start = '14:00:00', string $end = '17:00:00'): array
    {
        $tz = (string) config('app.timezone', 'Europe/Bucharest');
        if (strlen($start) === 5) {
            $start .= ':00';
        }
        if (strlen($end) === 5) {
            $end .= ':00';
        }
        $starts = Carbon::parse($ymd . ' ' . $start, $tz);
        $ends = Carbon::parse($ymd . ' ' . $end, $tz);
        if ($ends->timestamp <= $starts->timestamp) {
            $ends->addDay();
        }

        return [
            'playing_starts_at' => (int) $starts->timestamp,
            'playing_ends_at' => (int) $ends->timestamp,
        ];
    }

    /**
     * @return array{registration_allowed_until: int, cancellation_allowed_until: int}
     */
    protected function deadlineFieldsForStartsAt(int $eventStartsAt): array
    {
        $deadline = EventDeadlineTimestamps::defaultDeadline($eventStartsAt);

        return [
            'registration_allowed_until' => $deadline,
            'cancellation_allowed_until' => $deadline,
        ];
    }

    /**
     * @return array{registration_allowed_until: int, cancellation_allowed_until: int}
     */
    protected function deadlineFieldsForDate(string $dateYmd, string $start = '12:00:00'): array
    {
        $tz = (string) config('app.timezone', 'Europe/Bucharest');
        if (strlen($start) === 5) {
            $start .= ':00';
        }
        $startsAt = (int) Carbon::parse($dateYmd . ' ' . $start, $tz)->timestamp;

        return $this->deadlineFieldsForStartsAt($startsAt);
    }

    protected function attachCover(PadelEvent $event, string $path = 'events/factory/cover.png'): EventCoverImage
    {
        return EventCoverImage::factory()->create([
            'event_id' => $event->id_event,
            'cover_path' => $path,
            'aspect_ratio' => 1.5,
            'sort_order' => 0,
        ]);
    }

    protected function futureEventYmd(int $days = 10): string
    {
        return Carbon::now((string) config('app.timezone', 'Europe/Bucharest'))
            ->addDays($days)
            ->format('Y-m-d');
    }
}

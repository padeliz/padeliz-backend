<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\EventCoverImage;
use App\Models\EventDivision;
use App\Models\PadelEvent;
use App\Services\EventCalendarTiming;
use App\Services\EventDeadlineTimestamps;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

final class UnixEventFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function event(array $overrides = []): PadelEvent
    {
        return PadelEvent::factory()->create($overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function division(PadelEvent $event, array $overrides = []): EventDivision
    {
        return EventDivision::factory()->create(array_merge([
            'event_id' => $event->id_event,
            'check_in_at' => $event->event_starts_at,
            'playing_starts_at' => $event->event_starts_at,
            'playing_ends_at' => $event->event_ends_at,
        ], $overrides));
    }

    /**
     * @return array{event_starts_at: int, event_ends_at: int, registration_allowed_until: int, cancellation_allowed_until: int}
     */
    public static function eventBounds(
        string $dateYmd,
        string $startClock = '09:00:00',
        string $endClock = '18:00:00'
    ): array {
        $tz = EventCalendarTiming::timezone();
        if (strlen($startClock) === 5) {
            $startClock .= ':00';
        }
        if (strlen($endClock) === 5) {
            $endClock .= ':00';
        }
        $starts = Carbon::parse($dateYmd . ' ' . $startClock, $tz);
        $ends = Carbon::parse($dateYmd . ' ' . $endClock, $tz);
        if ($ends->timestamp <= $starts->timestamp) {
            $ends->addDay();
        }
        $startsAt = (int) $starts->timestamp;
        $deadline = EventDeadlineTimestamps::defaultDeadline($startsAt);

        return [
            'event_starts_at' => $startsAt,
            'event_ends_at' => (int) $ends->timestamp,
            'registration_allowed_until' => $deadline,
            'cancellation_allowed_until' => $deadline,
        ];
    }

    /**
     * @return array{playing_starts_at: int, playing_ends_at: int}
     */
    public static function playingBounds(PadelEvent $event, string $startClock, string $endClock): array
    {
        $day = EventCalendarDay::of((int) $event->event_starts_at);
        $tz = EventCalendarTiming::timezone();
        if (strlen($startClock) === 5) {
            $startClock .= ':00';
        }
        if (strlen($endClock) === 5) {
            $endClock .= ':00';
        }
        $starts = Carbon::parse($day . ' ' . $startClock, $tz);
        $ends = Carbon::parse($day . ' ' . $endClock, $tz);
        if ($ends->timestamp <= $starts->timestamp) {
            $ends->addDay();
        }

        return [
            'playing_starts_at' => (int) $starts->timestamp,
            'playing_ends_at' => (int) $ends->timestamp,
        ];
    }

    public static function withCover(PadelEvent $event, float $aspectRatio = 1.5, int $sortOrder = 0): EventCoverImage
    {
        $disk = (string) config('filesystems.user_uploads_disk', 'public');
        $path = 'events/' . $event->id_event . '/cover/test-' . $sortOrder . '.png';
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAYAAACddGYaAAAADklEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
        Storage::disk($disk)->put($path, $png !== false ? $png : 'x');

        return EventCoverImage::query()->create([
            'event_id' => $event->id_event,
            'cover_path' => $path,
            'aspect_ratio' => $aspectRatio,
            'sort_order' => $sortOrder,
        ]);
    }
}

/** @internal */
final class EventCalendarDay
{
    public static function of(int $unix): string
    {
        return Carbon::createFromTimestamp($unix, (string) config('app.timezone', 'Europe/Bucharest'))->toDateString();
    }
}

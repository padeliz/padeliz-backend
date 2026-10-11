<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

final class EventDeadlineTimestamps
{
    public static function startOfEventStartDay(int $eventStartsAt): int
    {
        return Carbon::createFromTimestamp($eventStartsAt, config('app.timezone'))->startOfDay()->timestamp;
    }

    public static function defaultDeadline(int $eventStartsAt): int
    {
        return self::startOfEventStartDay($eventStartsAt) - (48 * 3600);
    }

    /**
     * Resolve both required deadlines from a legacy nullable cancel-until value.
     *
     * @return array{0: int, 1: int} [registration_allowed_until, cancellation_allowed_until]
     */
    public static function fromLegacyCancelUntil(?int $legacyCancelUntil, int $eventStartsAt): array
    {
        $cap = $eventStartsAt - 1;

        if ($legacyCancelUntil === null) {
            $value = min(self::defaultDeadline($eventStartsAt), $cap);

            return [$value, $value];
        }

        $value = min($legacyCancelUntil, $cap);

        return [$value, $value];
    }

    /** @deprecated Use startOfEventStartDay */
    public static function startOfEditionDay(string $dateYmd): int
    {
        return Carbon::parse($dateYmd, config('app.timezone'))->startOfDay()->timestamp;
    }
}

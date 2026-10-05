<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

final class EventDeadlineTimestamps
{
    public static function startOfEditionDay(string $dateYmd): int
    {
        return Carbon::parse($dateYmd, config('app.timezone'))->startOfDay()->timestamp;
    }

    public static function defaultDeadline(string $dateYmd): int
    {
        return self::startOfEditionDay($dateYmd) - (48 * 3600);
    }

    /**
     * Resolve both required deadlines from a legacy nullable cancel-until value.
     *
     * @return array{0: int, 1: int} [registration_allowed_until, cancellation_allowed_until]
     */
    public static function fromLegacyCancelUntil(?int $legacyCancelUntil, string $dateYmd): array
    {
        $cap = self::startOfEditionDay($dateYmd);

        if ($legacyCancelUntil === null) {
            $value = self::defaultDeadline($dateYmd);

            return [$value, $value];
        }

        $value = min($legacyCancelUntil, $cap);

        return [$value, $value];
    }
}

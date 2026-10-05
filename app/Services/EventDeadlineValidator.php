<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class EventDeadlineValidator
{
    /**
     * @param  array{registration_allowed_until?: mixed, cancellation_allowed_until?: mixed, date?: mixed}  $effective
     */
    public static function assertOrdering(array $effective): void
    {
        $registration = isset($effective['registration_allowed_until'])
            ? (int) $effective['registration_allowed_until']
            : null;
        $cancellation = isset($effective['cancellation_allowed_until'])
            ? (int) $effective['cancellation_allowed_until']
            : null;
        $dateYmd = isset($effective['date'])
            ? substr((string) $effective['date'], 0, 10)
            : null;

        if ($registration === null || $cancellation === null || $dateYmd === null || $dateYmd === '') {
            return;
        }

        $errors = [];
        $startOfDay = EventDeadlineTimestamps::startOfEditionDay($dateYmd);

        if ($cancellation > $registration) {
            $errors['cancellation_allowed_until'] = [
                'Cancellation deadline must be on or before the registration deadline.',
            ];
        }

        if ($registration > $startOfDay) {
            $errors['registration_allowed_until'] = [
                'Registration deadline must be on or before the start of the edition date.',
            ];
        }

        if ($cancellation > $startOfDay) {
            $errors['cancellation_allowed_until'] = array_merge(
                $errors['cancellation_allowed_until'] ?? [],
                ['Cancellation deadline must be on or before the start of the edition date.']
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

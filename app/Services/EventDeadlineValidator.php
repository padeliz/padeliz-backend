<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class EventDeadlineValidator
{
    /**
     * @param  array{registration_allowed_until?: mixed, cancellation_allowed_until?: mixed, event_starts_at?: mixed}  $effective
     */
    public static function assertOrdering(array $effective): void
    {
        $registration = isset($effective['registration_allowed_until'])
            ? (int) $effective['registration_allowed_until']
            : null;
        $cancellation = isset($effective['cancellation_allowed_until'])
            ? (int) $effective['cancellation_allowed_until']
            : null;
        $eventStartsAt = isset($effective['event_starts_at'])
            ? (int) $effective['event_starts_at']
            : null;

        if ($registration === null || $cancellation === null || $eventStartsAt === null) {
            return;
        }

        $errors = [];

        if ($cancellation > $registration) {
            $errors['cancellation_allowed_until'] = [
                'Cancellation deadline must be on or before the registration deadline.',
            ];
        }

        if ($registration >= $eventStartsAt) {
            $errors['registration_allowed_until'] = [
                'Registration deadline must be before the event start.',
            ];
        }

        if ($cancellation >= $eventStartsAt) {
            $errors['cancellation_allowed_until'] = array_merge(
                $errors['cancellation_allowed_until'] ?? [],
                ['Cancellation deadline must be before the event start.']
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

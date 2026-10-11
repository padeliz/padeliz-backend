<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EventParticipation;

final class AttendancePosterInvalidation
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function shouldInvalidateForParticipationChange(
        EventParticipation $participation,
        array $validated,
    ): bool {
        if (array_key_exists('player_user_id', $validated)
            && (int) $validated['player_user_id'] !== (int) $participation->player_user_id) {
            return true;
        }

        if (array_key_exists('event_division_id', $validated)
            && (int) $validated['event_division_id'] !== (int) $participation->event_division_id) {
            return true;
        }

        if (array_key_exists('is_collecting_points', $validated)
            && (bool) $validated['is_collecting_points'] !== (bool) $participation->is_collecting_points) {
            return true;
        }

        return false;
    }
}

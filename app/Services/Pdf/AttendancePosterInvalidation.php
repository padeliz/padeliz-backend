<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EditionParticipation;

final class AttendancePosterInvalidation
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function shouldInvalidateForParticipationChange(
        EditionParticipation $participation,
        array $validated,
    ): bool {
        if (array_key_exists('player_id', $validated)
            && (int) $validated['player_id'] !== (int) $participation->player_id) {
            return true;
        }

        if (array_key_exists('edition_division_id', $validated)
            && (int) $validated['edition_division_id'] !== (int) $participation->edition_division_id) {
            return true;
        }

        if (array_key_exists('is_collecting_points', $validated)
            && (bool) $validated['is_collecting_points'] !== (bool) $participation->is_collecting_points) {
            return true;
        }

        return false;
    }
}

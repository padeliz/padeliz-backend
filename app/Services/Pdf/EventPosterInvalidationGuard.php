<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\PadelEvent;

final class EventPosterInvalidationGuard
{
    public function shouldInvalidateEditionId(int $editionId): bool
    {
        $event = PadelEvent::query()->find($editionId);
        if ($event === null) {
            return false;
        }

        if ($event->finalized_at !== null) {
            return false;
        }

        $lastEditionId = $this->lastEditionId();
        if ($lastEditionId === null) {
            return false;
        }

        return $editionId === $lastEditionId;
    }

    public function lastEditionId(): ?int
    {
        $row = PadelEvent::query()
            ->orderByDesc('date')
            ->orderByDesc('id_event')
            ->first(['id_event']);

        if ($row === null) {
            return null;
        }

        return (int) $row->id_event;
    }
}

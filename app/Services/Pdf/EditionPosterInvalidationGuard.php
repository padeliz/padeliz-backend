<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Edition;

final class EditionPosterInvalidationGuard
{
    public function shouldInvalidateEditionId(int $editionId): bool
    {
        $edition = Edition::query()->find($editionId);
        if ($edition === null) {
            return false;
        }

        if ($edition->finalized_at !== null) {
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
        $row = Edition::query()
            ->orderByDesc('date')
            ->orderByDesc('id_edition')
            ->first(['id_edition']);

        if ($row === null) {
            return null;
        }

        return (int) $row->id_edition;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pdf;

/**
 * Resolved photo path for PDF cache compose.
 *
 * @param  null|int  $sourcePlayerProfileImageId  null means initials / ephemeral source
 */
final class PdfPlayerPhotoForCache
{
    public function __construct(
        public readonly string $absolutePath,
        public readonly ?int $sourcePlayerProfileImageId,
    ) {
    }

    public function isEphemeral(): bool
    {
        return $this->sourcePlayerProfileImageId === null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pdf;

/**
 * Resolved photo path for PDF cache compose.
 *
 * @param  null|int  $sourcePlayerProfileImageId  null means initials / no profile source
 * @param  bool  $deleteAfterUse  true when absolutePath is a temp file that must be unlinked
 */
final class PdfPlayerPhotoForCache
{
    public function __construct(
        public readonly string $absolutePath,
        public readonly ?int $sourcePlayerProfileImageId,
        public readonly bool $deleteAfterUse = false,
    ) {
    }

    public function isEphemeral(): bool
    {
        return $this->deleteAfterUse;
    }
}

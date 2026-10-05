<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EventDivisionPoster;

final class PosterPdfResult
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $filename,
        public readonly ?EventDivisionPoster $poster = null,
    ) {
    }
}

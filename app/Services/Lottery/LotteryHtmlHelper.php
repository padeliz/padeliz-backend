<?php

declare(strict_types=1);

namespace App\Services\Lottery;

use App\Models\PadelEvent;

final class LotteryHtmlHelper
{
    public static function isPrizeVideo(?string $pathOrFilename): bool
    {
        if ($pathOrFilename === null || $pathOrFilename === '') {
            return false;
        }

        $ext = strtolower(pathinfo($pathOrFilename, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm', 'mov'], true);
    }

    /**
     * Leave {{edition.next.*}} placeholders unreplaced when next edition is missing (no crash).
     */
    public static function replaceEditionNextPlaceholders(?string $text, ?PadelEvent $nextEdition): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        if ($nextEdition === null) {
            return $text;
        }

        $nextName = (string) ($nextEdition->name ?? '');
        $date = $nextEdition->date;
        $nextDateShort = '';
        if ($date !== null) {
            $nextDateShort = $date instanceof \DateTimeInterface
                ? $date->format('d M Y')
                : date('d M Y', strtotime((string) $date));
        }

        return str_replace(
            ['{{edition.next.name}}', '{{edition.next.date.short}}'],
            [$nextName, $nextDateShort],
            $text
        );
    }
}

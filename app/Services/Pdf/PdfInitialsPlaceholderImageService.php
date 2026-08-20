<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Temporary square initials PNG used as PDF cache compose source when a player has no usable profile photo.
 */
final class PdfInitialsPlaceholderImageService
{
    public const SIZE = 300;

    private const BG_COLOR = [0x0A, 0x1A, 0x2F];

    private const TEXT_COLOR = [255, 255, 255];

    private const FONT_SIZE = 120;

    private const STORAGE_DIR = 'app/tmp/pdf-initials-placeholders';

    public function createTemp(int $playerId, string $name): string
    {
        $initials = $this->initials($name);
        $fontPath = resource_path('fonts/DejaVuSans-Bold.ttf');
        if (! is_file($fontPath)) {
            throw new RuntimeException('Missing initials font at resources/fonts/DejaVuSans-Bold.ttf');
        }

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        if ($canvas === false) {
            throw new RuntimeException('Failed to create initials canvas.');
        }

        $bg = imagecolorallocate($canvas, self::BG_COLOR[0], self::BG_COLOR[1], self::BG_COLOR[2]);
        $fg = imagecolorallocate($canvas, self::TEXT_COLOR[0], self::TEXT_COLOR[1], self::TEXT_COLOR[2]);
        imagefill($canvas, 0, 0, $bg);

        $bbox = imagettfbbox(self::FONT_SIZE, 0, $fontPath, $initials);
        if ($bbox === false) {
            imagedestroy($canvas);

            throw new RuntimeException('Failed to measure initials text.');
        }

        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];
        $x = (int) ((self::SIZE - $textWidth) / 2) - $bbox[0];
        $y = (int) ((self::SIZE - $textHeight) / 2) + $textHeight;

        imagettftext($canvas, self::FONT_SIZE, 0, $x, $y, $fg, $fontPath, $initials);

        $dir = storage_path(self::STORAGE_DIR);
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            imagedestroy($canvas);

            throw new RuntimeException('Failed to create initials temp directory.');
        }

        $path = $dir . '/' . uniqid('player_' . $playerId . '_', true) . '.png';
        if (! imagepng($canvas, $path)) {
            imagedestroy($canvas);

            throw new RuntimeException('Failed to write initials PNG.');
        }
        imagedestroy($canvas);

        return $path;
    }

    public function initials(string $name): string
    {
        $parts = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1));
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = mb_substr($parts[array_key_last($parts)], 0, 1);

        return mb_strtoupper($first . $last);
    }
}

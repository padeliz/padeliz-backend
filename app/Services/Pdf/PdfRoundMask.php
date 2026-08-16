<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Ellipse round-mask for square PDF avatar/couple canvases (MiniTour-style PNG alpha).
 * No white-crop fallback — corners become fully transparent.
 */
final class PdfRoundMask
{
    /**
     * Apply a circular alpha mask in-place on a square truecolor GD image.
     *
     * @param  \GdImage  $canvas
     */
    public static function apply($canvas): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        if ($width <= 0 || $height <= 0 || $width !== $height) {
            throw new RuntimeException('PdfRoundMask requires a square truecolor canvas.');
        }

        $size = $width;
        $mask = imagecreatetruecolor($size, $size);
        if ($mask === false) {
            throw new RuntimeException('Failed to create round mask.');
        }

        imagealphablending($mask, false);
        $black = imagecolorallocate($mask, 0, 0, 0);
        $white = imagecolorallocate($mask, 255, 255, 255);
        imagefill($mask, 0, 0, $black);
        imagefilledellipse($mask, (int) ($size / 2), (int) ($size / 2), $size, $size, $white);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $maskColor = imagecolorat($mask, $x, $y) & 0xFF;
                if ($maskColor < 128) {
                    imagesetpixel($canvas, $x, $y, $transparent);
                }
            }
        }

        imagedestroy($mask);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
    }

    /**
     * @param  \GdImage  $image
     * @return array{alpha: int, red: int, green: int, blue: int}
     */
    public static function sampleRgba($image, int $x, int $y): array
    {
        $rgba = imagecolorat($image, $x, $y);
        $colors = imagecolorsforindex($image, $rgba);

        return [
            'alpha' => (int) ($colors['alpha'] ?? 0),
            'red' => (int) ($colors['red'] ?? 0),
            'green' => (int) ($colors['green'] ?? 0),
            'blue' => (int) ($colors['blue'] ?? 0),
        ];
    }
}

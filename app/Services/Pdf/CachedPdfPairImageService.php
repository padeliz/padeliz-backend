<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\CachedPdfPairImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds side-by-side couple photos for MATCHES posters (GD).
 * Mode A upserts cached_pdf_pair_images; Mode B uses temp files only.
 */
final class CachedPdfPairImageService
{
    public const SQUARE_SIZE = 200;

    private const STORAGE_PREFIX = 'cached-pdf-pair-images';

    /**
     * @return string Absolute filesystem path usable by mPDF
     */
    public function resolvePath(
        int $player1Id,
        int $player2Id,
        string $player1PhotoAbsPath,
        string $player2PhotoAbsPath,
        bool $player1Collecting,
        bool $player2Collecting,
        bool $persist,
        ?int $player1SourceProfileImageId = null,
        ?int $player2SourceProfileImageId = null,
        ?int $player1UpdatedAt = null,
        ?int $player2UpdatedAt = null,
    ): string {
        [
            $leftId,
            $rightId,
            $leftPath,
            $rightPath,
            $leftCollecting,
            $rightCollecting,
            $needsFlip,
            $leftSourceId,
            $rightSourceId,
            $leftUpdatedAt,
            $rightUpdatedAt,
        ] = $this->normalizePair(
            $player1Id,
            $player2Id,
            $player1PhotoAbsPath,
            $player2PhotoAbsPath,
            $player1Collecting,
            $player2Collecting,
            $player1SourceProfileImageId,
            $player2SourceProfileImageId,
            $player1UpdatedAt,
            $player2UpdatedAt,
        );

        if ($persist) {
            $pair = CachedPdfPairImage::query()->firstOrNew([
                'player_1_id' => $leftId,
                'player_2_id' => $rightId,
                'player_1_is_collecting_points' => $leftCollecting,
                'player_2_is_collecting_points' => $rightCollecting,
            ]);

            $disk = $pair->storageDisk();
            $needsRegen = ! $pair->exists
                || $pair->picture_path === null
                || $pair->picture_path === ''
                || ! Storage::disk($disk)->exists((string) $pair->picture_path)
                || $this->sourceIdChanged($pair->source_player_1_profile_image_id, $leftSourceId)
                || $this->sourceIdChanged($pair->source_player_2_profile_image_id, $rightSourceId)
                || $this->needsFreshnessRegen(
                    $leftPath,
                    $rightPath,
                    $leftSourceId,
                    $rightSourceId,
                    $leftUpdatedAt,
                    $rightUpdatedAt,
                    $pair,
                );

            if ($needsRegen) {
                if (! $pair->exists) {
                    $pair->save();
                }

                $png = $this->composePng($leftPath, $rightPath, $leftCollecting, $rightCollecting);
                $relative = self::STORAGE_PREFIX . '/' . $pair->id_cached_pdf_pair_image . '/picture/couple.png';
                Storage::disk($disk)->put($relative, $png, ['visibility' => 'public']);
                $pair->picture_path = $relative;
                $pair->source_player_1_profile_image_id = $leftSourceId;
                $pair->source_player_2_profile_image_id = $rightSourceId;
                $pair->updated_at = time();
                $pair->save();
            }

            $absolute = Storage::disk($disk)->path((string) $pair->picture_path);
            if ($needsFlip) {
                return $this->writeTempFlipped($absolute);
            }

            return $absolute;
        }

        $png = $this->composePng($leftPath, $rightPath, $leftCollecting, $rightCollecting);
        $temp = $this->writeTempPng($png);
        if ($needsFlip) {
            $flipped = $this->writeTempFlipped($temp);
            @unlink($temp);

            return $flipped;
        }

        return $temp;
    }

    /**
     * @return array{0: int, 1: int, 2: string, 3: string, 4: bool, 5: bool, 6: bool, 7: ?int, 8: ?int, 9: ?int, 10: ?int}
     */
    private function normalizePair(
        int $player1Id,
        int $player2Id,
        string $player1PhotoAbsPath,
        string $player2PhotoAbsPath,
        bool $player1Collecting,
        bool $player2Collecting,
        ?int $player1SourceProfileImageId,
        ?int $player2SourceProfileImageId,
        ?int $player1UpdatedAt,
        ?int $player2UpdatedAt,
    ): array {
        if ($player1Id <= $player2Id) {
            return [
                $player1Id,
                $player2Id,
                $player1PhotoAbsPath,
                $player2PhotoAbsPath,
                $player1Collecting,
                $player2Collecting,
                false,
                $player1SourceProfileImageId,
                $player2SourceProfileImageId,
                $player1UpdatedAt,
                $player2UpdatedAt,
            ];
        }

        return [
            $player2Id,
            $player1Id,
            $player2PhotoAbsPath,
            $player1PhotoAbsPath,
            $player2Collecting,
            $player1Collecting,
            true,
            $player2SourceProfileImageId,
            $player1SourceProfileImageId,
            $player2UpdatedAt,
            $player1UpdatedAt,
        ];
    }

    private function sourceIdChanged(mixed $stored, ?int $current): bool
    {
        $storedId = $stored === null || $stored === '' ? null : (int) $stored;

        return $storedId !== $current;
    }

    private function needsFreshnessRegen(
        string $leftPath,
        string $rightPath,
        ?int $leftSourceId,
        ?int $rightSourceId,
        ?int $leftUpdatedAt,
        ?int $rightUpdatedAt,
        CachedPdfPairImage $pair,
    ): bool {
        $cacheFreshness = max((int) $pair->inserted_at, (int) ($pair->updated_at ?? 0));

        if ($leftSourceId === null) {
            if ($leftUpdatedAt !== null && $leftUpdatedAt > $cacheFreshness) {
                return true;
            }
        } else {
            $leftMtime = @filemtime($leftPath) ?: 0;
            if ($leftMtime > $cacheFreshness) {
                return true;
            }
        }

        if ($rightSourceId === null) {
            if ($rightUpdatedAt !== null && $rightUpdatedAt > $cacheFreshness) {
                return true;
            }
        } else {
            $rightMtime = @filemtime($rightPath) ?: 0;
            if ($rightMtime > $cacheFreshness) {
                return true;
            }
        }

        return false;
    }

    public function composePng(
        string $leftPath,
        string $rightPath,
        bool $leftCollecting,
        bool $rightCollecting,
    ): string {
        $left = $this->loadResized($leftPath);
        $right = $this->loadResized($rightPath);

        if (! $leftCollecting) {
            $left = $this->applyOpacity($left, 0.5);
        }
        if (! $rightCollecting) {
            $right = $this->applyOpacity($right, 0.5);
        }

        $half = (int) (self::SQUARE_SIZE / 2);
        $leftCrop = $this->centerHalfCrop($left);
        $rightCrop = $this->centerHalfCrop($right);

        $canvas = imagecreatetruecolor(self::SQUARE_SIZE, self::SQUARE_SIZE);
        if ($canvas === false) {
            throw new RuntimeException('Failed to create pair image canvas.');
        }
        imagealphablending($canvas, true);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        imagecopy($canvas, $leftCrop, 0, 0, 0, 0, $half, self::SQUARE_SIZE);
        imagecopy($canvas, $rightCrop, $half, 0, 0, 0, $half, self::SQUARE_SIZE);

        PdfRoundMask::apply($canvas);
        $line = imagecolorallocate($canvas, 255, 255, 255);
        imagesetthickness($canvas, 3);
        imageline($canvas, $half, 0, $half, self::SQUARE_SIZE, $line);
        imagesavealpha($canvas, true);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        imagedestroy($left);
        imagedestroy($right);
        imagedestroy($leftCrop);
        imagedestroy($rightCrop);
        imagedestroy($canvas);

        return $png;
    }

    /** @return \GdImage */
    public function loadResized(string $path)
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Unable to read player photo: ' . $path);
        }
        $image = @imagecreatefromstring($data);
        if ($image === false) {
            $jpeg = function_exists('imagecreatefromjpeg');
            $hint = $jpeg
                ? 'Expected JPEG/PNG/GIF/WebP.'
                : 'GD appears to lack JPEG support — rebuild the Laravel Docker image (libjpeg + docker-php-ext-configure gd --with-jpeg).';

            throw new RuntimeException(
                'Unable to decode player photo as an image (' . $hint . ') Path: ' . $path
            );
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            throw new RuntimeException('Invalid player photo dimensions: ' . $path);
        }

        if ($width > $height) {
            $newHeight = self::SQUARE_SIZE;
            $newWidth = (int) round($width * (self::SQUARE_SIZE / $height));
        } elseif ($width === $height) {
            $newWidth = $newHeight = self::SQUARE_SIZE;
        } else {
            $newWidth = self::SQUARE_SIZE;
            $newHeight = (int) round($height * (self::SQUARE_SIZE / $width));
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($resized === false) {
            imagedestroy($image);

            throw new RuntimeException('Failed to resize player photo.');
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /** @param \GdImage $image @return \GdImage */
    private function centerHalfCrop($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $crop = min($width, $height);
        $srcX = (int) ($crop / 4);
        $half = (int) ($crop / 2);

        $out = imagecreatetruecolor((int) (self::SQUARE_SIZE / 2), self::SQUARE_SIZE);
        if ($out === false) {
            throw new RuntimeException('Failed to crop pair half.');
        }
        imagecopyresampled(
            $out,
            $image,
            0,
            0,
            $srcX,
            0,
            (int) (self::SQUARE_SIZE / 2),
            self::SQUARE_SIZE,
            $half,
            $crop
        );

        return $out;
    }

    /** @param \GdImage $image @return \GdImage */
    private function applyOpacity($image, float $opacity)
    {
        if ($opacity >= 1.0) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        imagealphablending($image, true);
        $alpha = (int) round(127 * (1 - $opacity));
        $overlay = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
        imagefilledrectangle($image, 0, 0, $width, $height, $overlay);

        return $image;
    }

    private function writeTempPng(string $png): string
    {
        $dir = storage_path('app/tmp/cached-pdf-pair-images');
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException('Failed to create temp pair-images directory.');
        }
        $path = $dir . '/' . uniqid('pair_', true) . '.png';
        if (file_put_contents($path, $png) === false) {
            throw new RuntimeException('Failed to write temp pair image.');
        }

        return $path;
    }

    private function writeTempFlipped(string $absolutePath): string
    {
        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            throw new RuntimeException('Unable to read pair image for flip.');
        }
        $image = @imagecreatefromstring($data);
        if ($image === false) {
            throw new RuntimeException('Unable to decode pair image for flip.');
        }
        imageflip($image, IMG_FLIP_HORIZONTAL);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $this->writeTempPng($png);
    }
}

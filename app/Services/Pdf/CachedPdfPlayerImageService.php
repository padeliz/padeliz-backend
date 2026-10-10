<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\CachedPdfPlayerImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds circular single-player avatars for RANKING posters (GD).
 * Mode A upserts cached_pdf_player_images keyed by player + collecting;
 * Mode B uses temp files only. Non-collecting avatars get 50% opacity (pair parity).
 */
class CachedPdfPlayerImageService
{
    public const SQUARE_SIZE = 200;

    private const STORAGE_PREFIX = 'cached-pdf-player-images';

    /**
     * @return string Absolute filesystem path usable by mPDF
     */
    public function resolvePath(
        int $playerId,
        string $photoAbsPath,
        bool $playerIsCollecting,
        bool $persist,
        ?int $sourcePlayerProfileImageId = null,
        ?int $playerUpdatedAt = null,
    ): string {
        if ($persist) {
            $row = CachedPdfPlayerImage::query()->firstOrNew([
                'player_id' => $playerId,
                'player_is_collecting_points' => $playerIsCollecting,
            ]);

            $disk = $row->storageDisk();
            $needsRegen = ! $row->exists
                || $row->picture_path === null
                || $row->picture_path === ''
                || ! Storage::disk($disk)->exists((string) $row->picture_path)
                || $this->sourceIdChanged($row->source_player_profile_image_id, $sourcePlayerProfileImageId)
                || $this->needsFreshnessRegen($photoAbsPath, $sourcePlayerProfileImageId, $playerUpdatedAt, $row);

            if ($needsRegen) {
                if (! $row->exists) {
                    $row->save();
                }

                $png = $this->composePng($photoAbsPath, $playerIsCollecting);
                $relative = self::STORAGE_PREFIX . '/' . $row->id_cached_pdf_player_image . '/picture/avatar.png';
                Storage::disk($disk)->put($relative, $png, ['visibility' => 'public']);
                $row->picture_path = $relative;
                $row->source_player_profile_image_id = $sourcePlayerProfileImageId;
                $row->updated_at = time();
                $row->save();
            }

            return Storage::disk($disk)->path((string) $row->picture_path);
        }

        return $this->writeTempPng($this->composePng($photoAbsPath, $playerIsCollecting));
    }

    public function composePng(string $photoAbsPath, bool $playerIsCollecting = true): string
    {
        $resized = $this->loadCoverSquare($photoAbsPath);
        if (! $playerIsCollecting) {
            $resized = $this->applyOpacity($resized, 0.5);
        }

        $canvas = imagecreatetruecolor(self::SQUARE_SIZE, self::SQUARE_SIZE);
        if ($canvas === false) {
            imagedestroy($resized);

            throw new RuntimeException('Failed to create player avatar canvas.');
        }
        imagealphablending($canvas, true);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $resized, 0, 0, 0, 0, self::SQUARE_SIZE, self::SQUARE_SIZE);
        imagedestroy($resized);

        PdfRoundMask::apply($canvas);
        imagesavealpha($canvas, true);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        return $png;
    }

    private function sourceIdChanged(mixed $stored, ?int $current): bool
    {
        $storedId = $stored === null || $stored === '' ? null : (int) $stored;

        return $storedId !== $current;
    }

    private function needsFreshnessRegen(
        string $photoAbsPath,
        ?int $sourcePlayerProfileImageId,
        ?int $playerUpdatedAt,
        CachedPdfPlayerImage $row,
    ): bool {
        $cacheFreshness = max((int) $row->inserted_at, (int) ($row->updated_at ?? 0));

        if ($sourcePlayerProfileImageId === null) {
            return $playerUpdatedAt !== null && $playerUpdatedAt > $cacheFreshness;
        }

        $sourceMtime = @filemtime($photoAbsPath) ?: 0;

        return $sourceMtime > $cacheFreshness;
    }

    /** @return \GdImage */
    private function loadCoverSquare(string $path)
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Unable to read player photo: ' . $path);
        }
        $image = @imagecreatefromstring($data);
        if ($image === false) {
            throw new RuntimeException('Unable to decode player photo as an image. Path: ' . $path);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            throw new RuntimeException('Invalid player photo dimensions: ' . $path);
        }

        $scale = max(self::SQUARE_SIZE / $width, self::SQUARE_SIZE / $height);
        $newWidth = (int) max(1, round($width * $scale));
        $newHeight = (int) max(1, round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($resized === false) {
            imagedestroy($image);

            throw new RuntimeException('Failed to resize player photo.');
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        $srcX = (int) max(0, ($newWidth - self::SQUARE_SIZE) / 2);
        $srcY = (int) max(0, ($newHeight - self::SQUARE_SIZE) / 2);
        $cropped = imagecreatetruecolor(self::SQUARE_SIZE, self::SQUARE_SIZE);
        if ($cropped === false) {
            imagedestroy($resized);

            throw new RuntimeException('Failed to crop player photo.');
        }
        imagecopy($cropped, $resized, 0, 0, $srcX, $srcY, self::SQUARE_SIZE, self::SQUARE_SIZE);
        imagedestroy($resized);

        return $cropped;
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
        $dir = storage_path('app/tmp/cached-pdf-player-images');
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException('Failed to create temp player-images directory.');
        }
        $path = $dir . '/' . uniqid('player_', true) . '.png';
        if (file_put_contents($path, $png) === false) {
            throw new RuntimeException('Failed to write temp player image.');
        }

        return $path;
    }
}

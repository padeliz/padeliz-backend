<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Player;
use App\Services\PlayerProfileImage\PlayerProfileImageResolver;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves an on-disk photo for PDF cached avatar/pair compose (real profile or initials temp).
 */
final class PdfPlayerPhotoForCacheResolver
{
    public function __construct(
        private readonly PlayerProfileImageResolver $profileResolver = new PlayerProfileImageResolver(),
        private readonly PdfInitialsPlaceholderImageService $initials = new PdfInitialsPlaceholderImageService(),
    ) {
    }

    public function resolve(Player $player): PdfPlayerPhotoForCache
    {
        $player->loadMissing('profileImages');
        $profile = $this->profileResolver->resolveForPdf($player);

        if ($profile !== null && $profile->profile_path !== null && $profile->profile_path !== '') {
            $abs = Storage::disk($profile->storageDisk())->path((string) $profile->profile_path);
            if (is_file($abs) && $this->isDecodableImage($abs)) {
                return new PdfPlayerPhotoForCache($abs, (int) $profile->id);
            }
        }

        $temp = $this->initials->createTemp(
            (int) $player->id_player,
            (string) $player->name,
        );

        return new PdfPlayerPhotoForCache($temp, null);
    }

    private function isDecodableImage(string $absolutePath): bool
    {
        $data = @file_get_contents($absolutePath);
        if ($data === false || $data === '') {
            return false;
        }
        $image = @imagecreatefromstring($data);
        if ($image === false) {
            return false;
        }
        imagedestroy($image);

        return true;
    }
}

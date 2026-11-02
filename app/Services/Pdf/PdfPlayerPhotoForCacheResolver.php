<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\User;
use App\Services\PlayerProfileImage\PlayerProfileImageResolver;
use App\Services\Storage\UserUploadsDisk;

/**
 * Resolves an on-disk photo for PDF cached avatar/pair compose (real profile or initials temp).
 */
class PdfPlayerPhotoForCacheResolver
{
    public function __construct(
        private readonly PlayerProfileImageResolver $profileResolver = new PlayerProfileImageResolver(),
        private readonly PdfInitialsPlaceholderImageService $initials = new PdfInitialsPlaceholderImageService(),
        private readonly ?UserUploadsDisk $uploads = null,
    ) {
    }

    public function resolve(User $player): PdfPlayerPhotoForCache
    {
        $player->loadMissing('profileImages');
        $profile = $this->profileResolver->resolveForPdf($player);

        if ($profile !== null && $profile->profile_path !== null && $profile->profile_path !== '') {
            try {
                $uploads = $this->uploads ?? app(UserUploadsDisk::class);
                $local = $uploads->localPathForRead((string) $profile->profile_path);
                if ($this->isDecodableImage($local['path'])) {
                    return new PdfPlayerPhotoForCache(
                        $local['path'],
                        (int) $profile->id,
                        $local['delete_after'],
                    );
                }
                if ($local['delete_after']) {
                    @unlink($local['path']);
                }
            } catch (\Throwable) {
                // Fall through to initials.
            }
        }

        $temp = $this->initials->createTemp(
            (int) $player->id_user,
            (string) $player->name,
        );

        return new PdfPlayerPhotoForCache($temp, null, true);
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

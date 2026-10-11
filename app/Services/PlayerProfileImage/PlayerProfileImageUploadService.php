<?php

declare(strict_types=1);

namespace App\Services\PlayerProfileImage;

use App\Models\User;
use App\Models\PlayerProfileImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PlayerProfileImageUploadService
{
    public function __construct(
        private readonly PlayerProfileImagePrimaryService $primaryService,
    ) {
    }

    /**
     * @param  array{
     *     uploaded_via: string,
     *     uploaded_via_import_id?: null|int,
     *     uploaded_via_level_assessment_id?: null|int,
     *     original_filename?: null|string,
     *     is_pdf_primary?: bool
     * }  $provenance
     */
    public function upload(User $player, UploadedFile $file, array $provenance): PlayerProfileImage
    {
        $this->assertWithinCaps($player, $provenance['uploaded_via_level_assessment_id'] ?? null);

        $disk = config('filesystems.user_uploads_disk', 'public');
        $storedPath = null;
        $requestedPrimary = (bool) ($provenance['is_pdf_primary'] ?? false);

        return DB::transaction(function () use (
            $player,
            $file,
            $provenance,
            $disk,
            &$storedPath,
            $requestedPrimary,
        ): PlayerProfileImage {
            $sortOrder = $player->profileImages()->count();

            $image = PlayerProfileImage::create([
                'player_user_id' => $player->id_user,
                'profile_path' => '',
                'sort_order' => $sortOrder,
                'uploaded_via' => $provenance['uploaded_via'],
                'uploaded_via_import_id' => $provenance['uploaded_via_import_id'] ?? null,
                'uploaded_via_level_assessment_id' => $provenance['uploaded_via_level_assessment_id'] ?? null,
                'original_filename' => $provenance['original_filename'] ?? null,
                'is_pdf_primary' => false,
            ]);

            $storedPath = Storage::disk($disk)->putFile(
                'player-profile-images/' . $image->getKey() . '/profile',
                $file,
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $image->update(['profile_path' => $storedPath]);
            $this->primaryService->applyPrimaryOnUpload($player, $image, $requestedPrimary);
            $image->refresh();

            return $image;
        });
    }

    public function assertWithinCaps(User $player, ?int $levelAssessmentId): void
    {
        if ($player->profileImages()->count() >= PlayerProfileImage::MAX_PER_PLAYER) {
            throw new \RuntimeException(
                'Maximum number of profile images (' . PlayerProfileImage::MAX_PER_PLAYER . ') reached. Delete an existing image first.'
            );
        }

        if ($levelAssessmentId === null) {
            return;
        }

        $assessmentCount = $player->profileImages()
            ->where('uploaded_via_level_assessment_id', $levelAssessmentId)
            ->count();

        if ($assessmentCount >= PlayerProfileImage::MAX_PER_PLAYER_ASSESSMENT) {
            throw new \RuntimeException(
                'Maximum number of profile images (' . PlayerProfileImage::MAX_PER_PLAYER_ASSESSMENT . ') reached for this level assessment. Delete an existing image first.'
            );
        }
    }
}

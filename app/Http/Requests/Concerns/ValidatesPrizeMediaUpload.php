<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ValidatesPrizeMediaUpload
{
    public const MAX_PRIZE_MEDIA_KILOBYTES = 30720;

    /**
     * @return array<string, mixed>
     */
    protected function prizeMediaRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,gif,webp,mp4,webm,mov',
                'max:' . self::MAX_PRIZE_MEDIA_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function prizeMediaMessages(): array
    {
        $maxMb = (int) (self::MAX_PRIZE_MEDIA_KILOBYTES / 1024);

        return [
            'file.required' => 'Please select a media file to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.mimes' => 'The file must be an image (JPEG, PNG, GIF, WebP) or video (MP4, WebM, MOV).',
            'file.max' => 'The file must not exceed ' . self::MAX_PRIZE_MEDIA_KILOBYTES . ' KB (' . $maxMb . ' MB).',
        ];
    }
}

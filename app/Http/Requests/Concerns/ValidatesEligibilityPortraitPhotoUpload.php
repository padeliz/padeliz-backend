<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\EligibilityPortraitPhoto;

trait ValidatesEligibilityPortraitPhotoUpload
{
    /**
     * @return array<string, mixed>
     */
    protected function portraitPhotoRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:' . EligibilityPortraitPhoto::MAX_FILE_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function portraitPhotoMessages(): array
    {
        $maxMb = (int) (EligibilityPortraitPhoto::MAX_FILE_KILOBYTES / 1024);
        $phpUploadLimit = ini_get('upload_max_filesize') ?: 'unknown';

        return [
            'file.required' => 'Please select an image to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.uploaded' => "The image did not upload completely. It may exceed the server's PHP limit (upload_max_filesize is {$phpUploadLimit}) or this endpoint's maximum ({$maxMb} MB). Try a smaller or compressed file.",
            'file.image' => 'The file must be an image.',
            'file.mimes' => 'The image must be a JPEG, PNG, GIF, or WebP file.',
            'file.max' => 'The image must not exceed ' . EligibilityPortraitPhoto::MAX_FILE_KILOBYTES . ' KB (' . $maxMb . ' MB).',
        ];
    }
}

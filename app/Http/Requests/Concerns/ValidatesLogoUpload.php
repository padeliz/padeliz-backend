<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ValidatesLogoUpload
{
    public const MAX_LOGO_KILOBYTES = 5120;

    /**
     * @return array<string, mixed>
     */
    protected function logoRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:' . self::MAX_LOGO_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function logoMessages(): array
    {
        $maxMb = (int) (self::MAX_LOGO_KILOBYTES / 1024);

        return [
            'file.required' => 'Please select a logo image to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.mimes' => 'The logo must be an image (JPEG, PNG, GIF, WebP).',
            'file.max' => 'The logo must not exceed ' . self::MAX_LOGO_KILOBYTES . ' KB (' . $maxMb . ' MB).',
        ];
    }
}

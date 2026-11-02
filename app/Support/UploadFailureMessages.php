<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\UserUploadImageUnreadableException;
use App\Exceptions\UserUploadStoreException;
use Throwable;

/**
 * Domain-facing upload failure messages (no infrastructure details).
 */
final class UploadFailureMessages
{
    public static function for(Throwable $e, string $noun): string
    {
        if ($e instanceof UserUploadImageUnreadableException) {
            return 'Could not read image dimensions from the uploaded file.';
        }

        if ($e instanceof UserUploadStoreException) {
            return self::storeFailed($noun);
        }

        $message = $e->getMessage();
        if (str_contains(strtolower($message), 'dimension')) {
            return 'Could not read image dimensions from the uploaded file.';
        }

        return self::storeFailed($noun);
    }

    public static function storeFailed(string $noun): string
    {
        return match ($noun) {
            'cover' => 'Could not store the cover image. Please try again.',
            'logo' => 'Could not store the logo. Please try again.',
            'media' => 'Could not store the media. Please try again.',
            'image' => 'Could not store the image. Please try again.',
            'files' => 'Could not store the files. Please try again.',
            'file' => 'Could not store the file. Please try again.',
            default => 'Could not store the upload. Please try again.',
        };
    }
}

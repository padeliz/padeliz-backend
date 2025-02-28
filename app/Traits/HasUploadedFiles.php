<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;

trait HasUploadedFiles
{
    public function getUploadPath(string $type, string $lang = 'en', string $size = 'medium'): string
    {
        $modelName = strtolower(class_basename($this));
        return sprintf(
            '%s/.%s/%s/%s/%s/%s',
            public_path('uploads'),
            $modelName,
            $this->getKey(),
            $type,
            $lang,
            $size
        );
    }

    public function getFirstFile(string $type, string $lang = 'en', string $size = 'medium'): ?string
    {
        $path = $this->getUploadPath($type, $lang, $size);
        if (!File::exists($path)) {
            return null;
        }

        $files = File::files($path);
        return !empty($files) ? basename($files[0]) : null;
    }

    public function getFileUrl(string $type, string $lang = 'en', string $size = 'medium'): ?string
    {
        $file = $this->getFirstFile($type, $lang, $size);
        if (!$file) {
            return null;
        }

        return sprintf(
            '%s/.%s/%s/%s/%s/%s/%s',
            config('uploads.base_path'),
            strtolower(class_basename($this)),
            $this->getKey(),
            $type,
            $lang,
            $size,
            $file
        );
    }
}

<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;

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

    public function getUploadedFiles($type, string $lang = 'en', string $size = 'medium'): array
    {
        $path = $this->getUploadPath($type, $lang, $size);

        if (!File::exists($path)) {
            return [];
        }

        $files = File::files($path);

        return array_map(function ($file) {
            return $file->getBasename();
        }, $files);
    }

    public function getUploadedUrls(string $type, string $lang = 'en', string $size = 'medium'): array
    {
        return array_map(function ($file) use ($type, $lang, $size) {
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
        }, $this->getUploadedFiles($type, $lang, $size));
    }

    public function saveUploadedFile(UploadedFile $file, string $type, string $lang = 'en', string $size = 'medium'): string
    {
        $filename = $type . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $this->getUploadPath($type, $lang, $size);

        File::makeDirectory($path, 0755, true, true);
        $file->move($path, $filename);

        return $filename;
    }

    public function deleteUploadedFile(string $filename, string $type, string $lang = 'en', string $size = 'medium'): bool
    {
        $path = $this->getUploadPath($type, $lang, $size) . '/' . $filename;

        if (!File::exists($path)) {
            return false;
        }

        return File::delete($path);
    }
}

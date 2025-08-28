<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

trait HasUploadedFiles
{
    public function getUploadPath(string $type, string $lang = 'en', string $size = 'medium'): string
    {
        $basePath = config('uploads.base_path');
        if (empty($basePath)) {
            throw new \InvalidArgumentException('UPLOADS_BASE_PATH environment variable is required');
        }

        $modelName = strtolower(class_basename($this));

        return sprintf(
            '%s/.%s/%s/%s/%s/%s',
            $basePath,
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

        $absolutePath = base_path($path);

        if (!File::exists($absolutePath)) {
            return [];
        }

        try {
            $files = File::files($absolutePath);
        } catch (\Exception $e) {
            Log::error('HasUploadedFiles::getUploadedFiles - Error reading directory', [
                'path' => $absolutePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }

        if (empty($files)) {
            return [];
        }

        return array_map(function ($file) {
            return $file->getBasename();
        }, $files);
    }

    public function getUploadedUrls(string $type, string $lang = 'en', string $size = 'medium'): array
    {
        $basePath = config('uploads.base_path');
        if (empty($basePath)) {
            throw new \InvalidArgumentException('UPLOADS_BASE_PATH environment variable is required');
        }

        $uploadedFiles = $this->getUploadedFiles($type, $lang, $size);

        return array_map(function ($file) use ($type, $lang, $size, $basePath) {
            $baseUrl = rtrim(config('app.url'), '/');

            // Remove 'public/' from the path for URL generation
            $urlPath = str_replace('public/', '', $basePath);

            $uploadsUrl = $baseUrl . '/' . ltrim($urlPath, '/');

            return sprintf(
                '%s/.%s/%s/%s/%s/%s/%s',
                $uploadsUrl,
                strtolower(class_basename($this)),
                $this->getKey(),
                $type,
                $lang,
                $size,
                $file
            );
        }, $uploadedFiles);
    }

    public function saveUploadedFile(UploadedFile $file, string $type, string $lang = 'en', string $size = 'medium'): string
    {
        $filename = $type . '-' . microtime(true) . '.' . $file->getClientOriginalExtension();
        $path = $this->getUploadPath($type, $lang, $size);
        $absolutePath = base_path($path);

        File::makeDirectory($absolutePath, 0755, true, true);
        $file->move($absolutePath, $filename);

        return $filename;
    }

    public function deleteUploadedFile(string $filename, string $type, string $lang = 'en', string $size = 'medium'): bool
    {
        $path = $this->getUploadPath($type, $lang, $size) . '/' . $filename;
        $absolutePath = base_path($path);

        if (!File::exists($absolutePath)) {
            return false;
        }

        return File::delete($absolutePath);
    }
}

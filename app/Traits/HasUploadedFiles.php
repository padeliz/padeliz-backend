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
        try {
            $filename = $type . '-' . microtime(true) . '.' . $file->getClientOriginalExtension();
            $path = $this->getUploadPath($type, $lang, $size);
            $absolutePath = base_path($path);

            // Ensure the directory exists
            if (!File::exists($absolutePath)) {
                File::makeDirectory($absolutePath, 0755, true, true);
            }

            // Validate that the directory was created successfully
            if (!File::exists($absolutePath)) {
                throw new \RuntimeException('Failed to create upload directory');
            }

            // Check if the file is valid and readable
            if (!$file->isValid()) {
                throw new \RuntimeException('Uploaded file is not valid: ' . $file->getError());
            }

            // Move the file to the destination
            $file->move($absolutePath, $filename);

            // Verify the file was actually moved
            if (!File::exists($absolutePath . '/' . $filename)) {
                throw new \RuntimeException('File was not saved successfully');
            }

            return $filename;
        } catch (\Exception $e) {
            Log::error('HasUploadedFiles::saveUploadedFile - Error saving file', [
                'type' => $type,
                'lang' => $lang,
                'size' => $size,
                'original_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException('The image failed to upload: ' . $e->getMessage());
        }
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

<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\UserUploadImageUnreadableException;
use App\Exceptions\UserUploadStoreException;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserUploadsDisk
{
    public function diskName(): string
    {
        return (string) config('filesystems.user_uploads_disk', 'public');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function imageSizeFromUpload(UploadedFile $file): array
    {
        $local = $file->getRealPath();
        if (! is_string($local) || $local === '') {
            throw new UserUploadImageUnreadableException('Could not read image dimensions from the uploaded file.');
        }

        $info = @getimagesize($local);
        if ($info === false || ($info[0] ?? 0) <= 0 || ($info[1] ?? 0) <= 0) {
            throw new UserUploadImageUnreadableException('Could not read image dimensions from the uploaded file.');
        }

        return [(int) $info[0], (int) $info[1]];
    }

    public function temporaryUrl(string $path, ?DateTimeInterface $expiry = null): string
    {
        $disk = $this->disk();
        $driver = (string) config('filesystems.disks.' . $this->diskName() . '.driver', 'local');
        $supportsTemporary = $driver === 's3'
            && method_exists($disk, 'providesTemporaryUrls')
            && $disk->providesTemporaryUrls();

        if (! $supportsTemporary) {
            $url = $disk->url($path);
            if (preg_match('#^https?://#i', $url) === 1) {
                return $url;
            }

            return url($url);
        }

        $seconds = (int) config('filesystems.signed_url_ttl_seconds', 900);
        $expiry ??= now()->addSeconds($seconds);

        return $disk->temporaryUrl($path, $expiry);
    }

    /**
     * Prefer an existing local filesystem path; otherwise copy the object to a temp file.
     *
     * @return array{path: string, delete_after: bool}
     */
    public function localPathForRead(string $relative): array
    {
        $disk = $this->disk();
        if (! $disk->exists($relative)) {
            throw new UserUploadStoreException('Stored file is not available.');
        }

        $candidate = $disk->path($relative);
        if (is_file($candidate)) {
            return ['path' => $candidate, 'delete_after' => false];
        }

        return ['path' => $this->materializeToTemp($relative), 'delete_after' => true];
    }

    public function materializeToTemp(string $relative): string
    {
        $disk = $this->disk();
        if (! $disk->exists($relative)) {
            throw new UserUploadStoreException('Stored file is not available.');
        }

        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $suffix = is_string($extension) && $extension !== '' ? '.' . $extension : '';
        $temp = tempnam(sys_get_temp_dir(), 'padeliz_upload_');
        if ($temp === false) {
            throw new UserUploadStoreException('Could not create a temporary file.');
        }

        $target = $temp . $suffix;
        if ($target !== $temp) {
            if (! @rename($temp, $target)) {
                @unlink($temp);
                throw new UserUploadStoreException('Could not prepare a temporary file.');
            }
        }

        $stream = $disk->readStream($relative);
        if ($stream === false || $stream === null) {
            @unlink($target);
            throw new UserUploadStoreException('Could not read the stored file.');
        }

        $out = fopen($target, 'w+b');
        if ($out === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($target);
            throw new UserUploadStoreException('Could not write a temporary file.');
        }

        stream_copy_to_stream($stream, $out);
        if (is_resource($stream)) {
            fclose($stream);
        }
        fclose($out);

        return $target;
    }

    public function streamDownload(string $relative, string $filename, string $contentType): StreamedResponse
    {
        $disk = $this->disk();
        if (! $disk->exists($relative)) {
            abort(404, 'File is not available.');
        }

        $stream = $disk->readStream($relative);
        if ($stream === false || $stream === null) {
            abort(404, 'File is not available.');
        }

        return response()->streamDownload(static function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function putFile(string $directory, UploadedFile $file, array $options = []): string
    {
        $stored = $this->disk()->putFile($directory, $file, $options);
        if ($stored === false || $stored === null || $stored === '') {
            throw new UserUploadStoreException('Could not store the uploaded file.');
        }

        return $stored;
    }
}

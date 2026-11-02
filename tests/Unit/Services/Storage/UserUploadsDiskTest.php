<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage;

use App\Exceptions\UserUploadImageUnreadableException;
use App\Services\Storage\UserUploadsDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

final class UserUploadsDiskTest extends TestCase
{
    use FileUploadTestHelper;

    private UserUploadsDisk $uploads;

    private string $diskName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskName = 'public';
        config(['filesystems.user_uploads_disk' => $this->diskName]);
        Storage::fake($this->diskName);
        $this->uploads = new UserUploadsDisk();
    }

    public function test_image_size_from_upload_returns_dimensions(): void
    {
        $file = $this->createFakeImage('cover.png', 200, 100);

        $size = $this->uploads->imageSizeFromUpload($file);

        $this->assertSame([200, 100], $size);
    }

    public function test_image_size_from_upload_rejects_unreadable_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not_image_');
        $this->assertNotFalse($path);
        file_put_contents($path, 'not-an-image');

        $file = new UploadedFile($path, 'notes.txt', 'text/plain', null, true);

        $this->expectException(UserUploadImageUnreadableException::class);
        $this->uploads->imageSizeFromUpload($file);
    }

    public function test_temporary_url_on_local_disk_falls_back_to_url(): void
    {
        Storage::disk($this->diskName)->put('proofs/a.png', 'bytes');

        $url = $this->uploads->temporaryUrl('proofs/a.png');

        $this->assertStringEndsWith('/storage/proofs/a.png', $url);
        $this->assertMatchesRegularExpression('#^https?://#i', $url);
    }

    public function test_signed_url_ttl_seconds_defaults_to_900(): void
    {
        $this->assertSame(900, (int) config('filesystems.signed_url_ttl_seconds'));
    }

    public function test_materialize_to_temp_copies_object_bytes(): void
    {
        Storage::disk($this->diskName)->put('events/1/cover/a.png', 'png-bytes');

        $temp = $this->uploads->materializeToTemp('events/1/cover/a.png');

        $this->assertFileExists($temp);
        $this->assertSame('png-bytes', file_get_contents($temp));
        @unlink($temp);
    }

    public function test_stream_download_streams_file_contents(): void
    {
        Storage::disk($this->diskName)->put('posters/a.pdf', '%PDF-fake');

        $response = $this->uploads->streamDownload('posters/a.pdf', 'a.pdf', 'application/pdf');

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        $this->assertSame('%PDF-fake', $body);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_put_file_stores_and_returns_path(): void
    {
        $path = $this->uploads->putFile(
            'clubs/1/logo',
            $this->createFakeImage('logo.png'),
            ['visibility' => 'public']
        );

        $this->assertNotSame('', $path);
        Storage::disk($this->diskName)->assertExists($path);
        $this->assertSame('public', Storage::disk($this->diskName)->getVisibility($path));
    }

    public function test_local_path_for_read_uses_existing_local_file_without_delete_flag(): void
    {
        Storage::disk($this->diskName)->put('events/1/cover/a.png', 'png-bytes');

        $local = $this->uploads->localPathForRead('events/1/cover/a.png');

        $this->assertFalse($local['delete_after']);
        $this->assertFileExists($local['path']);
        $this->assertSame('png-bytes', file_get_contents($local['path']));
    }
}

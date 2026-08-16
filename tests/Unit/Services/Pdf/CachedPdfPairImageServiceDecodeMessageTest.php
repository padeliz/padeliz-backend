<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Services\Pdf\CachedPdfPairImageService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CachedPdfPairImageServiceDecodeMessageTest extends TestCase
{
    public function test_decode_failure_mentions_jpeg_or_gd(): void
    {
        $service = new CachedPdfPairImageService();
        $tmp = tempnam(sys_get_temp_dir(), 'notimg');
        file_put_contents($tmp, 'not-an-image');

        set_error_handler(static fn (): bool => true);

        try {
            $service->loadResized($tmp);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Unable to decode player photo as an image', $e->getMessage());
            $this->assertTrue(
                str_contains($e->getMessage(), 'JPEG') || str_contains($e->getMessage(), 'GD')
            );
        } finally {
            restore_error_handler();
            @unlink($tmp);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Services\Pdf\PdfRoundMask;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PdfRoundMaskTest extends TestCase
{
    public function test_corners_are_transparent_and_center_is_opaque(): void
    {
        $size = 200;
        $canvas = imagecreatetruecolor($size, $size);
        $this->assertNotFalse($canvas);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $red = imagecolorallocate($canvas, 255, 0, 0);
        imagefill($canvas, 0, 0, $red);

        PdfRoundMask::apply($canvas);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        $decoded = imagecreatefromstring($png);
        $this->assertNotFalse($decoded);
        imagesavealpha($decoded, true);

        $corner = PdfRoundMask::sampleRgba($decoded, 0, 0);
        $center = PdfRoundMask::sampleRgba($decoded, (int) ($size / 2), (int) ($size / 2));

        $this->assertGreaterThan(0, $corner['alpha'], 'Corner must be transparent (GD alpha > 0)');
        $this->assertSame(0, $center['alpha'], 'Center must be opaque');
        $this->assertSame(255, $center['red']);

        imagedestroy($decoded);
    }

    public function test_only_ellipse_mask_api_exists_no_white_crop_fallback(): void
    {
        $reflection = new ReflectionClass(PdfRoundMask::class);
        $methods = array_map(
            static fn ($m) => $m->getName(),
            $reflection->getMethods()
        );

        $this->assertContains('apply', $methods);
        $this->assertNotContains('applyWhiteCrop', $methods);
        $this->assertNotContains('whiteCrop', $methods);
        $this->assertNotContains('applyWhiteBackgroundCircle', $methods);
    }
}

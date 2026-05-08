<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Illuminate\Http\UploadedFile;

trait FileUploadTestHelper
{
    /**
     * Create a fake image file for testing
     */
    protected function createFakeImage(string $filename = 'test-image.png', int $width = 100, int $height = 100): UploadedFile
    {
        // Create a simple PNG image using GD
        $image = imagecreate($width, $height);

        // Set background color (white)
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);

        // Add some content (a simple rectangle)
        $blue = imagecolorallocate($image, 0, 0, 255);
        imagerectangle($image, 10, 10, $width - 10, $height - 10, $blue);

        // Create temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'test_image_');
        imagepng($image, $tempPath);
        imagedestroy($image);

        // Create UploadedFile
        $uploadedFile = new UploadedFile(
            $tempPath,
            $filename,
            'image/png',
            null,
            true
        );

        return $uploadedFile;
    }

    /**
     * Create a fake text file for testing
     */
    protected function createFakeTextFile(string $filename = 'test.txt', string $content = 'Test content'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_file_');
        file_put_contents($tempPath, $content);

        return new UploadedFile(
            $tempPath,
            $filename,
            'text/plain',
            null,
            true
        );
    }
}

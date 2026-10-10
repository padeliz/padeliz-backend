<?php

declare(strict_types=1);

namespace Tests\Support;

final class PdfFixtures
{
    public static function dir(): string
    {
        return dirname(__DIR__) . '/Fixtures/Pdf';
    }

    public static function path(string $filename): string
    {
        return self::dir() . '/' . $filename;
    }

    public static function bytes(string $filename): string
    {
        $path = self::path($filename);
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException('Missing PDF fixture: ' . $path);
        }

        return $bytes;
    }

    public static function minimalPdfBytes(): string
    {
        return self::bytes('minimal-one-page.pdf');
    }

    public static function solidRed40Bytes(): string
    {
        return self::bytes('solid-red-40.png');
    }

    public static function solidRed80Bytes(): string
    {
        return self::bytes('solid-red-80.png');
    }

    public static function solidBlue80Bytes(): string
    {
        return self::bytes('solid-blue-80.png');
    }

    public static function circularRed200Bytes(): string
    {
        return self::bytes('circular-red-200.png');
    }

    public static function solidRed220Path(): string
    {
        return self::path('solid-red-220x180.png');
    }

    public static function solidBlue180Path(): string
    {
        return self::path('solid-blue-180x220.png');
    }

    public static function solidRed200Path(): string
    {
        return self::path('solid-red-200.png');
    }
}

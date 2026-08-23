<?php

declare(strict_types=1);

namespace Tests\Support;

final class PdfDecompress
{
    public static function containsText(string $pdfBytes, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (str_contains($pdfBytes, $needle)) {
            return true;
        }

        foreach (self::decompressStreams($pdfBytes) as $stream) {
            if (str_contains($stream, $needle)) {
                return true;
            }
        }

        return self::containsUtf16BeText($pdfBytes, $needle);
    }

    public static function containsImageOperator(string $pdfBytes): bool
    {
        if (preg_match('/\sDo\s/', $pdfBytes) === 1) {
            return true;
        }

        foreach (self::decompressStreams($pdfBytes) as $stream) {
            if (preg_match('/\sDo\s/', $stream) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function countPages(string $pdfBytes): int
    {
        if (preg_match('/\/Type\s*\/Pages[^>]*\/Kids\s*\[([^\]]*)\]/s', $pdfBytes, $match) !== 1) {
            return 0;
        }

        $kids = $match[1];
        if (preg_match_all('/\d+\s+0\s+R/', $kids, $refs) === false) {
            return 0;
        }

        return count($refs[0]);
    }

    /**
     * @return list<string>
     */
    private static function decompressStreams(string $pdfBytes): array
    {
        $streams = [];
        $offset = 0;
        $length = strlen($pdfBytes);

        while (($start = strpos($pdfBytes, 'stream', $offset)) !== false) {
            $contentStart = $start + 6;
            if ($pdfBytes[$contentStart] === "\r" && $pdfBytes[$contentStart + 1] === "\n") {
                $contentStart += 2;
            } elseif ($pdfBytes[$contentStart] === "\n") {
                $contentStart += 1;
            }

            $end = strpos($pdfBytes, 'endstream', $contentStart);
            if ($end === false) {
                break;
            }

            $headerStart = max(0, $start - 256);
            $header = substr($pdfBytes, $headerStart, $start - $headerStart);
            $raw = substr($pdfBytes, $contentStart, $end - $contentStart);

            if (str_contains($header, '/FlateDecode')) {
                $inflated = @gzuncompress($raw);
                if ($inflated !== false) {
                    $streams[] = $inflated;
                }
            } else {
                $streams[] = $raw;
            }

            $offset = $end + 9;
            if ($offset >= $length) {
                break;
            }
        }

        return $streams;
    }

    private static function containsUtf16BeText(string $pdfBytes, string $needle): bool
    {
        $utf16 = self::utf16Be($needle);

        if (str_contains($pdfBytes, $utf16)) {
            return true;
        }

        foreach (self::decompressStreams($pdfBytes) as $stream) {
            if (str_contains($stream, $utf16)) {
                return true;
            }
        }

        return false;
    }

    private static function utf16Be(string $text): string
    {
        $encoded = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');

        return $encoded === false ? '' : $encoded;
    }
}

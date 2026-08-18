<?php

declare(strict_types=1);

namespace App\Support;

final class PlayerPhoneNormalizer
{
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        $dropTrunkZero = (bool) preg_match('/^\+\s*(\d{1,3})[\s.-]+0/', $trimmed);

        $compact = preg_replace('/[^\d+]/', '', $trimmed) ?? '';
        $compact = preg_replace('/(?!^)\+/', '', $compact) ?? $compact;

        $digits = preg_replace('/\D/', '', $compact) ?? '';
        if ($digits === '') {
            return '';
        }

        if ($dropTrunkZero && preg_match('/^(\d{1,3})0(\d+)$/', $digits, $matches) === 1) {
            $digits = $matches[1] . $matches[2];
        }

        return '+' . $digits;
    }
}

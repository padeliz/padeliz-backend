<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class PlayerNameSplitter
{
    public const MAX_PART_LENGTH = 50;

    /**
     * Split a display name into first / middle / last parts.
     *
     * @return array{first_name: string, middle_name: ?string, last_name: ?string}
     */
    public static function split(string $rawName): array
    {
        $name = trim($rawName);
        $name = preg_replace('/\s+\([^)]*\)\s*$/u', '', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '') {
            throw new InvalidArgumentException('Player name is empty after normalizing.');
        }

        $tokens = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($tokens);

        if ($count === 1) {
            $parts = [
                'first_name' => $tokens[0],
                'middle_name' => null,
                'last_name' => null,
            ];
        } elseif ($count === 2) {
            $parts = [
                'first_name' => $tokens[0],
                'middle_name' => null,
                'last_name' => $tokens[1],
            ];
        } else {
            $parts = [
                'first_name' => $tokens[0],
                'middle_name' => implode(' ', array_slice($tokens, 1, -1)),
                'last_name' => $tokens[$count - 1],
            ];
        }

        foreach ($parts as $key => $value) {
            if ($value !== null && mb_strlen($value) > self::MAX_PART_LENGTH) {
                throw new InvalidArgumentException(
                    sprintf('%s exceeds %d characters: "%s"', $key, self::MAX_PART_LENGTH, $value)
                );
            }
        }

        return $parts;
    }

    /**
     * Preview-safe split: empty names and overlong tokens do not throw.
     *
     * @return array{first_name: string, middle_name: ?string, last_name: ?string}
     */
    public static function splitForPreview(string $rawName): array
    {
        $name = trim($rawName);
        $name = preg_replace('/\s+\([^)]*\)\s*$/u', '', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '') {
            return [
                'first_name' => '',
                'middle_name' => null,
                'last_name' => null,
            ];
        }

        $tokens = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($tokens);

        if ($count === 1) {
            return [
                'first_name' => $tokens[0],
                'middle_name' => null,
                'last_name' => null,
            ];
        }

        if ($count === 2) {
            return [
                'first_name' => $tokens[0],
                'middle_name' => null,
                'last_name' => $tokens[1],
            ];
        }

        return [
            'first_name' => $tokens[0],
            'middle_name' => implode(' ', array_slice($tokens, 1, -1)),
            'last_name' => $tokens[$count - 1],
        ];
    }
}

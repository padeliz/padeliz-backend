<?php

declare(strict_types=1);

namespace App\Services\PlayerImport\Formats;

use App\Services\PlayerImport\Contracts\PlayerCsvFormat;

final class PlaytomicPlayerCsvFormat implements PlayerCsvFormat
{
    public function formatKey(): string
    {
        return 'playtomic';
    }

    public function supports(array $headers): bool
    {
        $normalized = array_map([$this, 'normalizeHeader'], $headers);

        return in_array('name', $normalized, true)
            && in_array('email', $normalized, true)
            && in_array('phone number', $normalized, true);
    }

    public function parseRow(array $assoc): array
    {
        $map = [];
        foreach ($assoc as $header => $value) {
            $map[$this->normalizeHeader((string) $header)] = is_string($value) ? $value : (string) $value;
        }

        return [
            'name' => trim($map['name'] ?? ''),
            'email' => trim($map['email'] ?? ''),
            'phone' => trim($map['phone number'] ?? ''),
        ];
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim($header));
    }
}

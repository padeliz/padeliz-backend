<?php

declare(strict_types=1);

namespace App\Services\PlayerLevel;

use App\Integrations\RankedinBpt\BptTopPlayersService;
use Illuminate\Validation\ValidationException;

/**
 * App Details step sends Playtomic / RankedIn profile URLs; we persist the extracted IDs.
 * Later saves may send the bare stored ID or a new parseable URL.
 */
final class AssessmentProfileIdNormalizer
{
    /**
     * @param  array<string, mixed>  $customFields
     * @return array<string, mixed>
     */
    public static function normalize(array $customFields): array
    {
        if (array_key_exists('playtomic_id', $customFields)) {
            $customFields['playtomic_id'] = self::playtomicId($customFields['playtomic_id']);
        }

        if (array_key_exists('rankedin_id', $customFields)) {
            $customFields['rankedin_id'] = self::rankedinId($customFields['rankedin_id']);
        }

        return $customFields;
    }

    private static function playtomicId(mixed $raw): mixed
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return $raw;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return $value;
        }

        if (self::looksLikeUrl($value) || str_contains(strtolower($value), 'playtomic')) {
            $id = CustomFieldValueMigrator::playtomicUrlToId($value);
            if ($id === null) {
                throw ValidationException::withMessages([
                    'custom_fields.playtomic_id' => ['Could not parse a Playtomic profile ID from that URL.'],
                ]);
            }

            return $id;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            throw ValidationException::withMessages([
                'custom_fields.playtomic_id' => ['Enter a Playtomic profile URL or numeric ID.'],
            ]);
        }

        return $value;
    }

    private static function rankedinId(mixed $raw): mixed
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return $raw;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return $value;
        }

        if (self::looksLikeUrl($value) || str_contains(strtolower($value), 'rankedin') || str_contains($value, '/player/')) {
            $id = BptTopPlayersService::rankedinIdFromPlayerUrl($value);
            if ($id === null) {
                throw ValidationException::withMessages([
                    'custom_fields.rankedin_id' => ['Could not parse a Rankedin player ID from that URL.'],
                ]);
            }

            return $id;
        }

        return $value;
    }

    private static function looksLikeUrl(string $value): bool
    {
        return str_contains($value, '://') || str_starts_with($value, 'www.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\PlayerLevel;

final class CustomFieldValueMigrator
{
    public const REFERENCE_YEAR = 2026;

    public static function ageToYearOfBirth(?string $valueText): ?int
    {
        if ($valueText === null || trim($valueText) === '') {
            return null;
        }

        if (! preg_match('/\d+/', $valueText, $matches)) {
            return null;
        }

        $age = (int) $matches[0];
        if ($age < 1 || $age > 120) {
            return null;
        }

        return self::REFERENCE_YEAR - $age;
    }

    public static function playtomicUrlToId(?string $valueText): ?string
    {
        if ($valueText === null || trim($valueText) === '') {
            return null;
        }

        if (! preg_match('#playtomic\.com/profile/user/(\d+)#i', $valueText, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<array{
     *     sport: string,
     *     years_of_playing?: float,
     *     matches_played?: int,
     *     lessons_had?: int
     * }>
     */
    public static function buildRacketExperience(
        ?string $padelMatchesPlayed,
        ?string $tennisExperience,
        ?string $racketLessons,
    ): array {
        $bySport = [];

        $padelMatches = self::extractConfidentInteger($padelMatchesPlayed);
        if ($padelMatches !== null) {
            $bySport['padel'] = ['sport' => 'padel', 'matches_played' => $padelMatches];
        }

        if (! self::isNegativeExperience($tennisExperience)) {
            $tennisMatches = self::extractTennisMatches($tennisExperience);
            $tennisYears = self::extractYears($tennisExperience);
            if ($tennisMatches !== null || $tennisYears !== null) {
                $entry = ['sport' => 'tennis'];
                if ($tennisYears !== null) {
                    $entry['years_of_playing'] = $tennisYears;
                }
                if ($tennisMatches !== null) {
                    $entry['matches_played'] = $tennisMatches;
                }
                $bySport['tennis'] = $entry;
            }
        }

        if (! self::isNegativeExperience($racketLessons)) {
            foreach (self::extractLessonsBySport($racketLessons) as $sport => $lessons) {
                if (! isset($bySport[$sport])) {
                    $bySport[$sport] = ['sport' => $sport];
                }
                $bySport[$sport]['lessons_had'] = $lessons;
            }
        }

        return array_values($bySport);
    }

    private static function isNegativeExperience(?string $text): bool
    {
        if ($text === null) {
            return true;
        }

        $normalized = mb_strtolower(trim($text));

        return in_array($normalized, ['nu', '0', '-', ''], true);
    }

    private static function extractConfidentInteger(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $trimmed)) {
            return (int) $trimmed;
        }

        // Single-number confident forms: 50+, ~40, aprox 40, Peste 15, maximum 10, Cca 50, Aproximativ 30
        if (preg_match('/^(?:~|aprox\.?\s*|aproximativ\s*|peste\s*|maximum\s*|cca\.?\s*|cca\s*)?(\d+)\+?$/iu', $trimmed, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function extractTennisMatches(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);
        if (preg_match('/^\d+$/', $trimmed)) {
            return (int) $trimmed;
        }

        if (preg_match('/^(\d+)\s*ori\b/iu', $trimmed, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function extractYears(?string $text): ?float
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*ani\b/iu', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*years?\b/iu', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return null;
    }

    /**
     * @return array<string, int> sport => lessons_had
     */
    private static function extractLessonsBySport(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $result = [];
        $lower = mb_strtolower($text);

        if (preg_match_all('/padel[^0-9]{0,40}?(\d+)/iu', $text, $matches, PREG_SET_ORDER)) {
            if (count($matches) === 1) {
                $result['padel'] = (int) $matches[0][1];
            }
        } elseif (str_contains($lower, 'padel') && preg_match('/^(\d+)$/', trim($text))) {
            $result['padel'] = (int) trim($text);
        }

        if (preg_match_all('/(?:tenis|tennis)[^0-9]{0,40}?(\d+)/iu', $text, $matches, PREG_SET_ORDER)) {
            if (count($matches) === 1) {
                $result['tennis'] = (int) $matches[0][1];
            }
        }

        $numberCount = preg_match_all('/\d+/', $text);
        if (preg_match('/badminton/iu', $text) && $numberCount === 1 && preg_match('/(\d+)/', $text, $m)) {
            $result['badminton'] = (int) $m[1];
        }

        if (preg_match('/ping[\s-]?pong|table tennis/iu', $text) && $numberCount === 1 && preg_match('/(\d+)/', $text, $m)) {
            $result['ping-pong'] = (int) $m[1];
        }

        // Bare integer lessons with no sport: skip (ambiguous)
        return $result;
    }
}

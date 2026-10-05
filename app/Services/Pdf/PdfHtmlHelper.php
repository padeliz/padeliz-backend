<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Services\Schedule\PointsPerMatchCalculator;

/**
 * Layout helpers ported from MiniTour PdfHtmlHelper.
 */
final class PdfHtmlHelper
{
    private const UNIT_HEIGHT = 1.0;

    private const WIDTH_NARROW = 0.35;

    private const WIDTH_WIDE = 0.90;

    private const WIDTH_SPACE = 0.28;

    private const WIDTH_DEFAULT = 0.55;

    /** @var array<string, float> */
    private const GLYPH_WIDTHS = [
        ' ' => self::WIDTH_SPACE,
        '.' => self::WIDTH_NARROW,
        ',' => self::WIDTH_NARROW,
        ':' => self::WIDTH_NARROW,
        "'" => self::WIDTH_NARROW,
        '-' => self::WIDTH_NARROW,
        'i' => self::WIDTH_NARROW,
        'I' => self::WIDTH_NARROW,
        'l' => self::WIDTH_NARROW,
        'j' => self::WIDTH_NARROW,
        't' => self::WIDTH_NARROW,
        'f' => self::WIDTH_NARROW,
        'r' => self::WIDTH_NARROW,
        'm' => self::WIDTH_WIDE,
        'w' => self::WIDTH_WIDE,
        'M' => self::WIDTH_WIDE,
        'W' => self::WIDTH_WIDE,
        '@' => self::WIDTH_WIDE,
        '%' => self::WIDTH_WIDE,
    ];

    public const PLAYERS_ROWS_SIZING = [
        '1' => [
            'img-width' => '145px',
            'points-slot-padding' => '20px 0',
            'total-points-padding' => '0 0 10px 10px',
            'total-points-font-size' => '150px',
            'match-slot-font-size' => '40px',
            'total-matches-padding' => '0 0 10px 5px',
            'total-matches-font-size' => '150px',
        ],
        '2' => [
            'img-width' => '135px',
            'points-slot-padding' => '0px 0',
            'total-points-padding' => '0 0 0px 10px',
            'total-points-font-size' => '150px',
            'match-slot-font-size' => '35px',
            'total-matches-padding' => '0 0 0px 5px',
            'total-matches-font-size' => '150px',
        ],
    ];

    public static function getActivitiesMarginTop(int $activitiesCount): int
    {
        if ($activitiesCount >= 27) {
            return 0;
        }
        if ($activitiesCount >= 25) {
            return 30;
        }
        if ($activitiesCount >= 23) {
            return 46;
        }
        if ($activitiesCount >= 21) {
            return 62;
        }
        if ($activitiesCount >= 19) {
            return 92;
        }
        if ($activitiesCount >= 17) {
            return 130;
        }
        if ($activitiesCount >= 15) {
            return 160;
        }
        if ($activitiesCount >= 13) {
            return 190;
        }
        if ($activitiesCount >= 11) {
            return 220;
        }
        if ($activitiesCount >= 9) {
            return 235;
        }
        if ($activitiesCount >= 7) {
            return 250;
        }

        return 265;
    }

    public static function getPlayersMarginTop(int $countPlayers, int $nrOfPlayerRows): int
    {
        if ($nrOfPlayerRows === 1) {
            return match (true) {
                $countPlayers <= 6 => 110,
                $countPlayers === 7 => 100,
                $countPlayers === 8 => 90,
                $countPlayers === 9 => 68,
                $countPlayers === 10 => 50,
                $countPlayers === 11 => 36,
                $countPlayers === 12 => 22,
                default => 15,
            };
        }

        return match (true) {
            $countPlayers <= 6 => 55,
            $countPlayers === 7 => 50,
            $countPlayers === 8 => 45,
            $countPlayers === 9 => 40,
            $countPlayers === 10 => 35,
            $countPlayers === 11 => 30,
            $countPlayers === 12 => 25,
            default => 20,
        };
    }

    public static function getFittedFontSize(string $text, float $targetWidth, float $maxHeight): int
    {
        if ($targetWidth <= 0 || $maxHeight <= 0) {
            return 1;
        }

        $unitWidth = self::estimateUnitWidth($text);
        if ($unitWidth <= 0) {
            return max(1, (int) floor($maxHeight / self::UNIT_HEIGHT));
        }

        $fromWidth = $targetWidth / $unitWidth;
        $fromHeight = $maxHeight / self::UNIT_HEIGHT;

        return max(1, (int) floor(min($fromWidth, $fromHeight)));
    }

    /**
     * @return list<int>
     */
    public static function splitMatchRankingRows(int $countPlayers, int $playerMatchesCount): array
    {
        if ($playerMatchesCount > 8) {
            $a = min(7, $playerMatchesCount - 7);

            return [$a, $playerMatchesCount - $a];
        }

        if ($countPlayers <= 5) {
            $a = min(4, $playerMatchesCount - 3);

            return [$a, $playerMatchesCount - $a];
        }

        return [$playerMatchesCount];
    }

    /** Even-round for print only; does not mutate stored points. */
    public static function evenRoundPointsForPrint(int $pointsPerMatch): int
    {
        return PointsPerMatchCalculator::evenRoundForPrint($pointsPerMatch);
    }

    public static function shortenPlayerName(string $fullName): string
    {
        return (string) preg_replace(
            '/(\b.+?\b)([a-zA-ZăâșşȘțȚî])[a-zA-ZăâșşȘțȚî]+\b/u',
            '$1$2.',
            $fullName
        );
    }

    public static function htmlShortName(string $shortName, bool $isCollectingPoints): string
    {
        $color = $isCollectingPoints ? '#000' : '#444';
        $inner = (string) preg_replace(
            '/([a-zA-ZăâșşȘțȚî]\.)/u',
            '<small style="font-weight: 400; color: #444;"><small>$1</small></small>',
            e($shortName)
        );

        return '<span style="color: ' . $color . ';">' . $inner . '</span>';
    }

    private static function estimateUnitWidth(string $text): float
    {
        $width = 0.0;
        $length = mb_strlen($text, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $width += self::GLYPH_WIDTHS[$char] ?? self::WIDTH_DEFAULT;
        }

        return $width;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EditionParticipation;

final class AttendancePdfLayout
{
    public const TITLE_TOP_PAD_MM = 2;

    public const PARTICIPANT_CONTENT_MM = 6;

    public const PARTICIPANT_GAP_MM = 3;

    public static function columnWidthPercent(int $columnCount): int
    {
        return (int) floor(100 / max(1, $columnCount));
    }

    public static function columnCountForEdCount(int $edCount): int
    {
        if ($edCount <= 0) {
            return 1;
        }

        return match ($edCount) {
            1, 2, 3 => 1,
            4 => 2,
            5, 6, 9 => 3,
            7, 8 => 4,
            default => 3,
        };
    }

    /**
     * @template T
     *
     * @param  list<T>  $cells
     * @return list<list<T>>
     */
    public static function rowBatches(array $cells, int $columnCount): array
    {
        if ($cells === []) {
            return [];
        }

        $columns = max(1, $columnCount);

        return array_map(
            static fn (array $chunk): array => array_values($chunk),
            array_chunk($cells, $columns)
        );
    }

    /**
     * @template T
     *
     * @param  list<T>  $editionDivisions
     * @return list<list<T>>
     */
    public static function pageBatches(array $editionDivisions, int $maxPerPage = 9): array
    {
        if ($editionDivisions === []) {
            return [[]];
        }

        return array_map(
            static fn (array $chunk): array => array_values($chunk),
            array_chunk($editionDivisions, max(1, $maxPerPage))
        );
    }

    /**
     * @param  iterable<EditionParticipation>  $participations
     * @return list<EditionParticipation>
     */
    public static function sortParticipationsByPlayerName(iterable $participations): array
    {
        $rows = is_array($participations) ? $participations : iterator_to_array($participations);
        usort(
            $rows,
            static function (EditionParticipation $left, EditionParticipation $right): int {
                $leftName = mb_strtolower((string) ($left->player?->name ?? ''));
                $rightName = mb_strtolower((string) ($right->player?->name ?? ''));

                return $leftName <=> $rightName;
            }
        );

        return $rows;
    }

    public static function formatOrdinalHeaderDate(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        $year = (int) date('Y', $timestamp);
        $month = date('F', $timestamp);
        $day = (int) date('j', $timestamp);
        $suffix = self::ordinalSuffix($day);

        return sprintf('%d %s %d%s', $year, $month, $day, $suffix);
    }

    private static function ordinalSuffix(int $day): string
    {
        if ($day >= 11 && $day <= 13) {
            return 'th';
        }

        return match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}

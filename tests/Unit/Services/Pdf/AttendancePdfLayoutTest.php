<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\EventParticipation;
use App\Services\Pdf\AttendancePdfLayout;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttendancePdfLayoutTest extends TestCase
{
    private const TZ = 'Europe/Bucharest';

    #[DataProvider('columnCountProvider')]
    public function test_column_count_for_ed_count(int $edCount, int $expectedColumns): void
    {
        $this->assertSame($expectedColumns, AttendancePdfLayout::columnCountForEdCount($edCount));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function columnCountProvider(): array
    {
        return [
            'zero' => [0, 1],
            'one' => [1, 1],
            'two' => [2, 1],
            'three' => [3, 1],
            'four' => [4, 2],
            'five' => [5, 3],
            'six' => [6, 3],
            'seven' => [7, 4],
            'eight' => [8, 4],
            'nine' => [9, 3],
            'ten default' => [10, 3],
        ];
    }

    public function test_page_batches_splits_more_than_nine_divisions(): void
    {
        $divisions = range(1, 10);

        $batches = AttendancePdfLayout::pageBatches($divisions);

        $this->assertCount(2, $batches);
        $this->assertCount(9, $batches[0]);
        $this->assertCount(1, $batches[1]);
        $this->assertSame(1, $batches[0][0]);
        $this->assertSame(10, $batches[1][0]);
    }

    public function test_page_batches_empty_returns_single_empty_batch(): void
    {
        $this->assertSame([[]], AttendancePdfLayout::pageBatches([]));
    }

    public function test_row_batches_splits_cells_by_column_count(): void
    {
        $cells = range(0, 5);

        $this->assertSame(
            [[0, 1, 2], [3, 4, 5]],
            AttendancePdfLayout::rowBatches($cells, 3)
        );
    }

    public function test_row_batches_four_cells_two_columns(): void
    {
        $cells = range(0, 3);

        $this->assertSame(
            [[0, 1], [2, 3]],
            AttendancePdfLayout::rowBatches($cells, 2)
        );
    }

    public function test_row_batches_seven_cells_four_columns(): void
    {
        $cells = range(0, 6);

        $this->assertSame(
            [[0, 1, 2, 3], [4, 5, 6]],
            AttendancePdfLayout::rowBatches($cells, 4)
        );
    }

    public function test_row_batches_single_cell(): void
    {
        $this->assertSame(
            [[0]],
            AttendancePdfLayout::rowBatches([0], 1)
        );
    }

    public function test_row_batches_empty_returns_empty(): void
    {
        $this->assertSame([], AttendancePdfLayout::rowBatches([], 3));
    }

    public function test_sort_participations_by_player_name_is_case_insensitive(): void
    {
        $zebra = new class {
            public string $name = 'Zebra Player';
        };
        $alpha = new class {
            public string $name = 'alpha Player';
        };
        $bravo = new class {
            public string $name = 'Bravo Player';
        };

        $make = static function (int $playerUserId, object $player): EventParticipation {
            $participation = new EventParticipation();
            $participation->player_user_id = $playerUserId;
            $participation->setRelation('player', $player);

            return $participation;
        };

        $sorted = AttendancePdfLayout::sortParticipationsByPlayerName([
            $make(3, $zebra),
            $make(1, $alpha),
            $make(2, $bravo),
        ]);

        $this->assertSame(
            [1, 2, 3],
            array_map(static fn (EventParticipation $p): int => (int) $p->player_user_id, $sorted)
        );
    }

    #[DataProvider('ordinalHeaderDateProvider')]
    public function test_format_ordinal_header_date(string $input, string $expected): void
    {
        $this->assertSame(
            $expected,
            AttendancePdfLayout::formatOrdinalHeaderDate($input, self::TZ)
        );
    }

    #[DataProvider('attendanceHeaderDateProvider')]
    public function test_format_attendance_header_date(
        int $startsAt,
        int $endsAt,
        int $nrOfDays,
        string $expectedDate,
        string $expectedTimes,
    ): void {
        $this->assertSame(
            $expectedDate,
            AttendancePdfLayout::formatAttendanceHeaderDate($startsAt, $endsAt, $nrOfDays, self::TZ)
        );
        $this->assertSame(
            $expectedTimes,
            AttendancePdfLayout::formatAttendanceHeaderTimes($startsAt, $endsAt, self::TZ)
        );
    }

    public function test_layout_spacing_constants(): void
    {
        $this->assertSame(2, AttendancePdfLayout::TITLE_TOP_PAD_MM);
        $this->assertSame(6, AttendancePdfLayout::PARTICIPANT_CONTENT_MM);
        $this->assertSame(3, AttendancePdfLayout::PARTICIPANT_GAP_MM);
    }

    #[DataProvider('columnWidthPercentProvider')]
    public function test_column_width_percent(int $columnCount, int $expectedPercent): void
    {
        $this->assertSame($expectedPercent, AttendancePdfLayout::columnWidthPercent($columnCount));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function columnWidthPercentProvider(): array
    {
        return [
            'one' => [1, 100],
            'two' => [2, 50],
            'three' => [3, 33],
            'four' => [4, 25],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ordinalHeaderDateProvider(): array
    {
        return [
            '15th' => ['2026-06-15', '2026 June 15th'],
            '1st' => ['2026-01-01', '2026 January 1st'],
            '2nd' => ['2026-03-02', '2026 March 2nd'],
            '3rd' => ['2026-04-03', '2026 April 3rd'],
            '11th' => ['2026-05-11', '2026 May 11th'],
            'invalid passthrough' => ['not-a-date', 'not-a-date'],
        ];
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int, 3: string, 4: string}>
     */
    public static function attendanceHeaderDateProvider(): array
    {
        $tz = self::TZ;

        $singleStart = Carbon::parse('2026-06-15 10:00:00', $tz)->timestamp;
        $singleEnd = Carbon::parse('2026-06-15 18:30:00', $tz)->timestamp;

        $sameMonthStart = Carbon::parse('2026-10-11 10:00:00', $tz)->timestamp;
        $sameMonthEnd = Carbon::parse('2026-10-12 18:00:00', $tz)->timestamp;

        $diffMonthStart = Carbon::parse('2026-10-28 10:00:00', $tz)->timestamp;
        $diffMonthEnd = Carbon::parse('2026-11-02 18:00:00', $tz)->timestamp;

        $diffYearStart = Carbon::parse('2026-12-31 10:00:00', $tz)->timestamp;
        $diffYearEnd = Carbon::parse('2027-01-02 18:00:00', $tz)->timestamp;

        return [
            'single day ordinal' => [$singleStart, $singleEnd, 1, '2026 June 15th', '10:00 – 18:30'],
            'same month year' => [$sameMonthStart, $sameMonthEnd, 2, '11-12 Oct 2026', '10:00 – 18:00'],
            'different month same year' => [$diffMonthStart, $diffMonthEnd, 6, '28 Oct - 2 Nov', '10:00 – 18:00'],
            'different year' => [$diffYearStart, $diffYearEnd, 3, '31 Dec 2026 - 2 Jan 2027', '10:00 – 18:00'],
        ];
    }
}

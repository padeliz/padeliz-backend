<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\PointsPerMatchCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PointsPerMatchCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: int}>
     */
    public static function formulaCases(): array
    {
        // hours = whole hours of resolved window; floor(ceil(110*h - 1 + 20/60) / rounds)
        return [
            '3h / 8 rounds' => ['14:00', '17:00', 8, (int) floor(ceil(110 * 3 - 1 + 20 / 60) / 8)],
            '2h / 6 rounds' => ['16:00', '18:00', 6, (int) floor(ceil(110 * 2 - 1 + 20 / 60) / 6)],
            '1h / 4 rounds' => ['12:00', '13:00', 4, (int) floor(ceil(110 * 1 - 1 + 20 / 60) / 4)],
            'sub-hour window yields 0 hours' => ['12:00', '12:45', 4, (int) floor(ceil(110 * 0 - 1 + 20 / 60) / 4)],
            'accepts H:i:s' => ['14:00:00', '17:00:00', 8, (int) floor(ceil(110 * 3 - 1 + 20 / 60) / 8)],
            'overnight ~6h' => ['18:00', '00:00', 8, (int) floor(ceil(110 * 6 - 1 + 20 / 60) / 8)],
            'end of day 24:00 ~6h' => ['18:00', '24:00:00', 8, (int) floor(ceil(110 * 6 - 1 + 20 / 60) / 8)],
        ];
    }

    #[DataProvider('formulaCases')]
    public function test_points_formula(string $start, string $end, int $rounds, int $expected): void
    {
        $this->assertSame($expected, PointsPerMatchCalculator::calculate($start, $end, $rounds));
    }

    public function test_zero_rounds_returns_zero(): void
    {
        $this->assertSame(0, PointsPerMatchCalculator::calculate('14:00', '17:00', 0));
    }

    public function test_invalid_time_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PointsPerMatchCalculator::calculate('nope', '17:00', 4);
    }

    public function test_even_round_for_print(): void
    {
        $this->assertSame(24, PointsPerMatchCalculator::evenRoundForPrint(24));
        $this->assertSame(24, PointsPerMatchCalculator::evenRoundForPrint(23));
        $this->assertSame(2, PointsPerMatchCalculator::evenRoundForPrint(1));
        $this->assertSame(0, PointsPerMatchCalculator::evenRoundForPrint(0));
    }

    public function test_apply_adjust(): void
    {
        $this->assertSame(12, PointsPerMatchCalculator::applyAdjust(10, 2));
        $this->assertSame(8, PointsPerMatchCalculator::applyAdjust(10, -2));
        $this->assertSame(0, PointsPerMatchCalculator::applyAdjust(1, -4));
    }

    public function test_finalize_preview_points(): void
    {
        $withAdjust = PointsPerMatchCalculator::finalizePreviewPoints(
            ['points_per_match' => 21, 'matches_count' => 4],
            2
        );
        $this->assertSame(23, $withAdjust['points_per_match']);
        $this->assertSame(2, $withAdjust['adjust']);
        $this->assertSame(24, $withAdjust['points_per_match_print']);
        $this->assertSame(4, $withAdjust['matches_count']);

        $zeroAdjust = PointsPerMatchCalculator::finalizePreviewPoints(
            ['points_per_match' => 21],
            0
        );
        $this->assertSame(21, $zeroAdjust['points_per_match']);
        $this->assertSame(0, $zeroAdjust['adjust']);
        $this->assertSame(22, $zeroAdjust['points_per_match_print']);
    }
}

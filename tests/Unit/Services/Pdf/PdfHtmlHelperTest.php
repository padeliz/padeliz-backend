<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Services\Pdf\PdfHtmlHelper;
use App\Services\Schedule\PointsPerMatchCalculator;
use PHPUnit\Framework\TestCase;

final class PdfHtmlHelperTest extends TestCase
{
    public function test_even_round_points_for_print(): void
    {
        $this->assertSame(24, PdfHtmlHelper::evenRoundPointsForPrint(24));
        $this->assertSame(24, PdfHtmlHelper::evenRoundPointsForPrint(23));
        $this->assertSame(2, PdfHtmlHelper::evenRoundPointsForPrint(1));
        $this->assertSame(0, PdfHtmlHelper::evenRoundPointsForPrint(0));
        $this->assertSame(
            PointsPerMatchCalculator::evenRoundForPrint(23),
            PdfHtmlHelper::evenRoundPointsForPrint(23)
        );
    }

    public function test_split_match_ranking_rows(): void
    {
        $this->assertSame([8], PdfHtmlHelper::splitMatchRankingRows(8, 8));
        $this->assertSame([3, 7], PdfHtmlHelper::splitMatchRankingRows(8, 10));
        $this->assertSame([3, 3], PdfHtmlHelper::splitMatchRankingRows(4, 6));
    }
}

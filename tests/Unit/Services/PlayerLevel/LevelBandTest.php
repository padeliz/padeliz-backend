<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerLevel;

use App\Services\PlayerLevel\LevelBand;
use PHPUnit\Framework\TestCase;

final class LevelBandTest extends TestCase
{
    public function test_catalog_covers_five_divisions_in_three_segments(): void
    {
        $catalog = LevelBand::catalog();

        $this->assertCount(15, $catalog);
        $this->assertSame('Division 5 Low', $catalog[0]->name);
        $this->assertSame('5.99', $catalog[0]->levelMin);
        $this->assertSame('5.67', $catalog[0]->levelMax);
        $this->assertSame('Division 1 High', $catalog[14]->name);
        $this->assertSame('1.33', $catalog[14]->levelMin);
        $this->assertSame('1.00', $catalog[14]->levelMax);
    }

    public function test_catalog_leaves_no_gap_between_adjacent_bands(): void
    {
        $catalog = LevelBand::catalog();

        for ($i = 1; $i < count($catalog); $i++) {
            $previousMax = (float) $catalog[$i - 1]->levelMax;
            $currentMin = (float) $catalog[$i]->levelMin;

            $this->assertEqualsWithDelta(0.01, round($previousMax - $currentMin, 2), 0.0001);
        }
    }

    public function test_midpoint_is_two_decimals(): void
    {
        $band = new LevelBand('Division 3 Mid', '3.66', '3.34');

        $this->assertSame('3.50', $band->midpoint());
    }

    public function test_contains_is_inclusive_on_both_bounds(): void
    {
        $band = new LevelBand('Division 3 Mid', '3.66', '3.34');

        $this->assertTrue($band->contains('3.66'));
        $this->assertTrue($band->contains('3.50'));
        $this->assertTrue($band->contains('3.34'));
        $this->assertFalse($band->contains('3.67'));
        $this->assertFalse($band->contains('3.33'));
    }

    public function test_find_containing_resolves_the_owning_band(): void
    {
        $this->assertSame('Division 4 High', LevelBand::findContaining('4.10')?->name);
        $this->assertSame('Division 5 Low', LevelBand::findContaining('5.99')?->name);
        $this->assertSame('Division 1 High', LevelBand::findContaining('1.00')?->name);
    }

    public function test_find_containing_returns_null_outside_the_catalog(): void
    {
        $this->assertNull(LevelBand::findContaining('0.99'));
        $this->assertNull(LevelBand::findContaining('6.00'));
    }

    public function test_division_attachments_point_at_known_bands(): void
    {
        $bandNames = array_map(
            static fn (LevelBand $band): string => $band->name,
            LevelBand::catalog()
        );

        $attachments = LevelBand::divisionAttachments();

        $this->assertSame('Division 5 Low', $attachments['Rookies']);
        $this->assertSame('Division 3 Low', $attachments['Challengers']);

        foreach ($attachments as $divisionName => $bandName) {
            $this->assertContains($bandName, $bandNames, "Division {$divisionName} points at an unknown band.");
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Schedule\Metrics;

/** CLI colour thresholds for ordering distribution columns. */
final class PlayerDistributionDisplay
{
    public const DISPLAY_EXCELLENT = 0.90;
    public const DISPLAY_GOOD = 0.80;
    public const DISPLAY_FAIR = 0.70;
}

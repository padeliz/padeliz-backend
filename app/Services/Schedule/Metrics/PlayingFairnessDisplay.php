<?php

declare(strict_types=1);

namespace App\Services\Schedule\Metrics;

/** CLI colour thresholds for playing-fairness columns. */
final class PlayingFairnessDisplay
{
    public const DISPLAY_GOOD = 0.90;
    public const DISPLAY_FAIR = 0.80;
    public const DISPLAY_MAX_PENALTY_GOOD = 0.08;
    public const DISPLAY_MAX_PENALTY_FAIR = 0.12;
}

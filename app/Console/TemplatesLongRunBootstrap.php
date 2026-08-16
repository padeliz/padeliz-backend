<?php

declare(strict_types=1);

namespace App\Console;

/**
 * PHP runtime limits for long-running offline template generation batches.
 */
final class TemplatesLongRunBootstrap
{
    public static function apply(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
    }
}

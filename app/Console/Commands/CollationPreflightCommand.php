<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Database\CollationPreflightService;
use Illuminate\Console\Command;

final class CollationPreflightCommand extends Command
{
    protected $signature = 'db:collation-preflight {--connection= : Database connection name}';

    protected $description = 'Detect UNIQUE index collisions under utf8mb4_0900_ai_ci';

    public function handle(CollationPreflightService $preflight): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $collisions = $preflight->detectUniqueCollisions($connection);
        $report = $preflight->formatCollisionReport($collisions);
        $this->line($report);

        return $collisions === [] ? self::SUCCESS : self::FAILURE;
    }
}

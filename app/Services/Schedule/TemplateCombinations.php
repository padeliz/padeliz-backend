<?php

declare(strict_types=1);

namespace App\Services\Schedule;

/**
 * Known mixed-template combination matrix and CLI display constants.
 *
 * Writer ownership lives in this backend. Offline Artisan commands:
 *
 * - `templates:generate` — add missing JSON under `resources/template-matches/v{N}/`
 * - `templates:regenerate` — overwrite combos (full bulk wipes `v{N}/` first)
 * - `templates:recalculate-quality-metrics` — migrate schema and refresh metrics in place
 * - `templates:versions` — list version dirs and catalog coverage
 * - `templates:metrics` — read-only inspection (mixed by default; `--fixed-teams=1` or
 *   `--include-fixed-teams` for fixed JSON)
 *
 * Fixed-teams templates are inspectable via metrics/manager APIs and regenerable via
 * `templates:generate` / `templates:regenerate` with `--fixed-teams=1`. Runtime schedule
 * generation (Mode A / Mode B) loads mixed templates only.
 *
 * Committed JSON under `resources/template-matches` is the catalog consumed at runtime.
 */
final class TemplateCombinations
{
    /** @var array<int, list<int>> */
    public const COMBINATIONS = [
        4 => [1, 2, 3],
        5 => [4],
        6 => [4],
        7 => [4],
        8 => [4, 6, 7],
        9 => [8],
        10 => [8],
        11 => [8],
        12 => [7, 8, 9],
        13 => [8],
        14 => [8],
        15 => [9, 12],
        16 => [8, 11, 12],
    ];

    public const STOP_REASON_TRIVIAL = 'TRIVIAL';
    public const STOP_REASON_DEADLINE = 'DEADLINE';
    public const STOP_REASON_FACTORIAL_COMPLETE = 'FACTORIAL_COMPLETE';
    public const STOP_REASON_PRUNE_INFEASIBLE = 'PRUNE_INFEASIBLE';

    /** @var array<string, string> */
    public const STOP_REASONS = [
        self::STOP_REASON_FACTORIAL_COMPLETE => 'exhausted',
        self::STOP_REASON_DEADLINE => 'deadline',
        self::STOP_REASON_TRIVIAL => 'trivial',
        self::STOP_REASON_PRUNE_INFEASIBLE => 'infeasible',
    ];

    public const DISPLAY_AVG_DIST_GREEN = 0.85;
    public const DISPLAY_AVG_DIST_YELLOW = 0.70;

    public static function stopReasonLabel(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        return self::STOP_REASONS[$key] ?? $key;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EditionDivisionStanding;
use App\Services\Standings\StandingsRanker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill standing ranks from existing wins/points_total (competition ranking).
 *
 * Only processes edition divisions that have at least one NULL rank.
 * Does not recompute wins/points or create missing standings.
 */
final class StandingsFillRanksCommand extends Command
{
    protected $signature = 'standings:fill-ranks
        {--dry-run : Compute ranks without writing}';

    protected $description = 'Fill edition_division_standings.rank from wins/points_total (NULL-rank EDs only)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $editionDivisionIds = EditionDivisionStanding::query()
            ->whereNull('rank')
            ->distinct()
            ->orderBy('edition_division_id')
            ->pluck('edition_division_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $edsProcessed = 0;
        $rowsUpdated = 0;
        $rowsSkipped = 0;

        foreach ($editionDivisionIds as $editionDivisionId) {
            $result = $this->processEditionDivision($editionDivisionId, $dryRun);
            $edsProcessed++;
            $rowsUpdated += $result['updated'];
            $rowsSkipped += $result['skipped'];
        }

        $prefix = $dryRun ? 'Dry-run: ' : '';
        $this->info($prefix . sprintf(
            'EDs processed: %d; rows %s: %d; rows skipped (already correct): %d',
            $edsProcessed,
            $dryRun ? 'would update' : 'updated',
            $rowsUpdated,
            $rowsSkipped
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    private function processEditionDivision(int $editionDivisionId, bool $dryRun): array
    {
        $run = function () use ($editionDivisionId, $dryRun): array {
            $standings = EditionDivisionStanding::query()
                ->where('edition_division_id', $editionDivisionId)
                ->get();

            $input = [];
            foreach ($standings as $standing) {
                $input[(int) $standing->id_standing] = [
                    'wins' => $standing->wins,
                    'points_total' => $standing->points_total,
                ];
            }

            $ranks = StandingsRanker::assignCompetitionRanks($input);

            $updated = 0;
            $skipped = 0;

            foreach ($standings as $standing) {
                $computed = $ranks[(int) $standing->id_standing];
                $current = $standing->rank;

                if ($current !== null && (int) $current === $computed) {
                    $skipped++;

                    continue;
                }

                $updated++;
                if (! $dryRun) {
                    $standing->rank = $computed;
                    $standing->save();
                }
            }

            return ['updated' => $updated, 'skipped' => $skipped];
        };

        if ($dryRun) {
            return $run();
        }

        return DB::transaction($run);
    }
}

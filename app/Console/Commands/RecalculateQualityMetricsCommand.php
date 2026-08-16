<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\MetricsFormatterTrait;
use App\Console\TemplateComboResolver;
use App\Services\Schedule\CompletedScheduleQualityCalculator;
use App\Services\Schedule\TemplateMatchesRepository;
use App\Services\Schedule\TemplateMatchesSchemaMigrator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Migrates template JSON to the current schema and recalculates quality metrics in place.
 */
final class RecalculateQualityMetricsCommand extends Command
{
    use MetricsFormatterTrait;

    protected $signature = 'templates:recalculate-quality-metrics
        {--templates-version= : Templates version to migrate/recalculate (required)}
        {--players= : Filter by players count (clean v{N}/ only)}
        {--partners= : Filter by opponents per player (clean v{N}/ only)}
        {--repeat= : Filter by repeat opponents (clean v{N}/ only)}
        {--fixed-teams= : Filter by fixed teams 0/1 (clean v{N}/ only)}
        {--courts= : Filter by court count (clean v{N}/ only)}';

    protected $description = 'Migrate template JSON schema and recalculate quality metrics in place';

    private TemplateMatchesRepository $repository;

    private TemplateMatchesSchemaMigrator $migrator;

    private CompletedScheduleQualityCalculator $qualityCalculator;

    public function __construct(
        ?TemplateMatchesRepository $repository = null,
        ?TemplateMatchesSchemaMigrator $migrator = null,
        ?CompletedScheduleQualityCalculator $qualityCalculator = null
    ) {
        parent::__construct();
        $this->repository = $repository ?? new TemplateMatchesRepository();
        $this->migrator = $migrator ?? new TemplateMatchesSchemaMigrator();
        $this->qualityCalculator = $qualityCalculator ?? new CompletedScheduleQualityCalculator();
    }

    public function handle(): int
    {
        $io = new SymfonyStyle($this->input, $this->output);

        try {
            $version = $this->parseRequiredTemplateVersion($this->option('templates-version'));
            $source = $this->repository->resolveVersionSourceDirectory($version);
            $filters = $this->parseOptionalFilters($this->input);

            if (! $source['isClean'] && $filters !== []) {
                throw new \InvalidArgumentException(sprintf(
                    'Filters are not allowed when the source directory has a suffix (%s). Run without filters to migrate the entire directory.',
                    $source['directoryName']
                ));
            }

            $files = $this->repository->listTemplateFilesInDirectory(
                $source['directoryName'],
                $source['isClean'] ? $filters : []
            );

            if ($files === []) {
                $io->warning(sprintf('No template JSON files found in %s.', $source['directoryName']));

                return self::SUCCESS;
            }

            $processed = 0;
            $recalculated = 0;
            $preservedNullMatches = 0;

            foreach ($files as $file) {
                $raw = file_get_contents($file['absolutePath']);
                if ($raw === false) {
                    throw new \RuntimeException('Could not read template file: ' . $file['absolutePath']);
                }

                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    throw new \RuntimeException('Invalid JSON in template file: ' . $file['absolutePath']);
                }

                $identity = [
                    'players' => $file['players'],
                    'partners' => $file['partners'],
                    'repeat' => $file['repeat'],
                    'courts' => $file['courts'],
                    'fixedTeams' => $file['fixedTeams'],
                ];

                $template = $this->migrator->migrate($decoded, $identity);
                $matches = $template->getMatches();

                if ($matches !== null) {
                    $quality = $this->qualityCalculator->compute(
                        $template->getPlayers(),
                        $template->getPartners(),
                        $template->getRepeat(),
                        $template->getCourts(),
                        $template->isFixedTeams(),
                        $matches
                    );
                    $template = $template->withRecalculatedQuality($quality);
                    $recalculated++;
                } else {
                    $preservedNullMatches++;
                }

                $this->repository->writeTemplateFile($file['absolutePath'], $template);

                $canonicalName = $this->repository->filenameForIdentity(
                    $file['players'],
                    $file['partners'],
                    $file['repeat'],
                    $file['courts'],
                    $file['fixedTeams']
                );
                if ($file['basename'] !== $canonicalName) {
                    $targetPath = dirname($file['absolutePath']) . DIRECTORY_SEPARATOR . $canonicalName;
                    $this->repository->renameTemplateFile($file['absolutePath'], $targetPath);
                }

                $processed++;
                $io->writeln(sprintf(
                    '  %s → %s (%s)',
                    $file['basename'],
                    $canonicalName,
                    $matches !== null ? 'quality recalculated' : 'matches null; quality preserved'
                ));
            }

            if (! $source['isClean']) {
                $cleanName = 'v' . $version;
                $this->repository->renameVersionDirectory($source['directoryName'], $cleanName);
                $io->success(sprintf(
                    'Processed %d file(s) (%d recalculated, %d matches-null). Renamed %s → %s.',
                    $processed,
                    $recalculated,
                    $preservedNullMatches,
                    $source['directoryName'],
                    $cleanName
                ));
            } else {
                $io->success(sprintf(
                    'Processed %d file(s) in v%d (%d recalculated, %d matches-null).',
                    $processed,
                    $version,
                    $recalculated,
                    $preservedNullMatches
                ));
            }

            return self::SUCCESS;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{
     *     players?: int,
     *     partners?: int,
     *     repeat?: int,
     *     courts?: int,
     *     fixedTeams?: bool
     * }
     */
    private function parseOptionalFilters(InputInterface $input): array
    {
        $resolver = new TemplateComboResolver();

        return $resolver->parseFilters($input);
    }
}

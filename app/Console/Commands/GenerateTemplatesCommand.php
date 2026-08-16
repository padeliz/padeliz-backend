<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\MetricsFormatterTrait;
use App\Console\TemplateComboResolver;
use App\Console\TemplatesLongRunBootstrap;
use App\Services\Schedule\Progress\GenerationProgress;
use App\Services\Schedule\Progress\MatchMakingProgress;
use App\Services\Schedule\Progress\OrderingProgress;
use App\Services\Schedule\Progress\PairingProgress;
use App\Services\Schedule\TemplateCombinations;
use App\Services\Schedule\TemplateMatches;
use App\Services\Schedule\TemplateMatchesGenerator;
use App\Services\Schedule\TemplateMatchesRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Additively generates template files into an explicit version directory
 * (`resources/template-matches/v{N}/`), skipping any combo whose JSON file already exists.
 * Requires `--templates-version=N`. Complement to {@see RegenerateTemplatesCommand}: never
 * wipes anything and is safe to re-run.
 */
final class GenerateTemplatesCommand extends Command
{
    use MetricsFormatterTrait;

    protected $signature = 'templates:generate
        {--templates-version= : Version directory to write (required; v{N}/ is created when missing)}
        {--with-stats : Include stats columns in the output tables}
        {--players= : Filter by players count}
        {--partners= : Filter by opponents per player}
        {--repeat= : Filter by repeat opponents (default 1)}
        {--fixed-teams= : Filter by fixed teams (0 or 1; default 0)}
        {--courts= : Filter by court count (default 1)}';

    protected $description = 'Additively generate missing template-matches JSON files (requires --templates-version)';

    private TemplateMatchesGenerator $generator;

    private TemplateMatchesRepository $repository;

    public function __construct(
        ?TemplateMatchesGenerator $generator = null,
        ?TemplateMatchesRepository $repository = null
    ) {
        parent::__construct();
        $this->generator = $generator ?? new TemplateMatchesGenerator();
        $this->repository = $repository ?? new TemplateMatchesRepository();
    }

    public function handle(): int
    {
        TemplatesLongRunBootstrap::apply();

        $io = new SymfonyStyle($this->input, $this->output);

        try {
            $writeVersion = $this->parseRequiredTemplateVersion($this->option('templates-version'));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $resolver = new TemplateComboResolver();
        try {
            $resolved = $resolver->resolve($this->input, TemplateCombinations::COMBINATIONS, false);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $this->repository->ensureVersionDirectory($writeVersion);
        try {
            $latestVersion = $this->repository->latestVersion();
        } catch (\RuntimeException) {
            $latestVersion = $writeVersion;
        }
        $combos = $resolved['combos'];

        $io->writeln(sprintf(
            '<info>Latest version:</info> v%d   <comment>Writing to:</comment> v%d   <comment>Combos:</comment> %d',
            $latestVersion,
            $writeVersion,
            count($combos)
        ));
        $io->writeln('<info>Base dir:</info> ' . $this->repository->getBaseDir());
        $io->newLine();

        if (count($combos) === 1) {
            $combo = $combos[0];
            if ($this->repository->hasAt(
                $writeVersion,
                $combo['players'],
                $combo['partners'],
                $combo['repeat'],
                $combo['courts'],
                $combo['fixedTeams']
            )) {
                $io->writeln(sprintf(
                    '<info>%s already exists in v%d -- skipping.</info>',
                    $this->repository->path(
                        $writeVersion,
                        $combo['players'],
                        $combo['partners'],
                        $combo['repeat'],
                        $combo['courts'],
                        $combo['fixedTeams']
                    ),
                    $writeVersion
                ));
                $io->writeln('<comment>Use templates:regenerate with the same filters to overwrite.</comment>');

                return self::SUCCESS;
            }
        }

        $rawCount = count($combos);
        $combos = array_values(array_filter(
            $combos,
            fn (array $c): bool => ! $this->repository->hasAt(
                $writeVersion,
                $c['players'],
                $c['partners'],
                $c['repeat'],
                $c['courts'],
                $c['fixedTeams']
            )
        ));
        $skipped = $rawCount - count($combos);
        if ($skipped > 0) {
            $io->writeln(sprintf(
                '<comment>Skipped %d combo%s already present in v%d/.</comment>',
                $skipped,
                $skipped === 1 ? '' : 's',
                $writeVersion
            ));
            $io->newLine();
        }

        if ($combos === []) {
            $io->writeln('<info>Nothing to generate -- every matching combo is already present.</info>');

            return self::SUCCESS;
        }

        $failures = [];
        $supportsSections = $this->output instanceof ConsoleOutputInterface;
        $totalCombos = count($combos);
        $summaryRows = [];
        $includeStatsColumns = (bool) $this->option('with-stats');

        foreach ($combos as $i => $combo) {
            $counterSection = null;
            $liveSection = null;

            if ($supportsSections) {
                /** @var ConsoleOutputInterface $output */
                $output = $this->output;
                $counterSection = $output->section();
                $liveSection = $output->section();
                $counterSection->writeln(sprintf(
                    '<info>[%d/%d]</info> (in progress)',
                    $i + 1,
                    $totalCombos
                ));
                $callback = $this->liveTableCallback(
                    $counterSection,
                    $liveSection,
                    $combo,
                    $i + 1,
                    $totalCombos,
                    $includeStatsColumns
                );
            } else {
                $this->output->writeln(sprintf(
                    '<info>[%d/%d]</info> players=%d partners=%d repeat=%d fixedTeams=%s',
                    $i + 1,
                    $totalCombos,
                    $combo['players'],
                    $combo['partners'],
                    $combo['repeat'],
                    $combo['fixedTeams'] ? 'true' : 'false'
                ));
                $callback = $this->bufferedFallbackCallback($this->output);
            }

            $this->generator->setProgressCallback($callback);

            try {
                $template = $this->generator->generate(
                    $combo['players'],
                    $combo['partners'],
                    $combo['repeat'],
                    $combo['courts'],
                    $combo['fixedTeams']
                );
            } finally {
                $this->generator->setProgressCallback(null);
            }

            $this->repository->save($writeVersion, $template);

            $savedPath = $this->repository->path(
                $writeVersion,
                $combo['players'],
                $combo['partners'],
                $combo['repeat'],
                $combo['courts'],
                $combo['fixedTeams']
            );
            if ($template->isEligible()) {
                $io->writeln(sprintf('<info>Saved:</info> %s', $savedPath));
            } else {
                $io->writeln(sprintf(
                    '<comment>Saved infeasible record (matches=null):</comment> %s',
                    $savedPath
                ));
            }

            if ($counterSection !== null) {
                $counterSection->overwrite(sprintf(
                    '<info>[%d/%d]</info> %s',
                    $i + 1,
                    $totalCombos,
                    $template->isEligible() ? '<info>(done)</info>' : '<error>(no eligible permutation)</error>'
                ));
            }
            if ($liveSection !== null) {
                $liveSection->clear();
                $this->renderLiveSnapshotTable(
                    $liveSection,
                    $template,
                    true,
                    null,
                    $includeStatsColumns
                );
            }

            $summaryRows[] = ['combo' => $combo, 'template' => $template];

            if (! $template->isEligible()) {
                $failures[] = $combo;
            }

            $this->output->writeln('');
        }

        $summaryTemplates = array_column($summaryRows, 'template');
        $layout = $this->resolveUnifiedTableLayout($combos, $summaryTemplates, $includeStatsColumns);
        $table = $this->makeUnifiedTable($this->output);
        $table->setHeaders($this->unifiedHeaders($layout));
        $this->writeTableContextLine($this->output, $layout);
        $this->output->writeln('');

        $previousPlayers = null;
        $minPartnersFairs = [];
        $avgPartnersFairs = [];
        $partnersVars = [];
        $meetingsVars = [];
        $minPlayingFairs = [];
        $avgPlayingFairs = [];
        $maxPlayingPenalties = [];
        $mins = [];
        $avgs = [];

        foreach ($summaryRows as $row) {
            $combo = $row['combo'];
            $template = $row['template'];
            $firstOfGroup = ($previousPlayers !== $combo['players']);

            if ($firstOfGroup && $previousPlayers !== null) {
                $table->addRow(array_fill(0, $layout['totalColumns'], new TableSeparator()));
            }

            $table->addRow($this->buildUnifiedRow(
                $template,
                $combo['players'],
                $combo['partners'],
                $firstOfGroup,
                $layout
            ));

            $minPartnersFairs[] = $template->getPairingQualityMinPartnersFairness();
            $avgPartnersFairs[] = $template->getPairingQualityAvgPartnersFairness();
            $partnersVars[] = $template->getPairingQualityPartnersCountVariation();
            $meetingsVars[] = $template->getMatchMakingQualityMeetingsVariation();
            $minPlayingFairs[] = $template->getMatchMakingQualityMinPlayingFairness();
            $avgPlayingFairs[] = $template->getMatchMakingQualityAvgPlayingFairness();
            $maxPlayingPenalties[] = $template->getMatchMakingQualityMaxPlayingFairnessPenalty();
            $mins[] = $template->getOrderingQualityMinDistribution();
            $avgs[] = $template->getOrderingQualityAvgDistribution();
            $previousPlayers = $combo['players'];
        }

        $table->addRow(array_fill(0, $layout['totalColumns'], new TableSeparator()));
        $table->addRow($this->buildAvgRow(
            $layout,
            $minPartnersFairs,
            $avgPartnersFairs,
            $partnersVars,
            $meetingsVars,
            $minPlayingFairs,
            $avgPlayingFairs,
            $maxPlayingPenalties,
            $mins,
            $avgs
        ));

        $io->newLine();
        $table->render();
        $io->newLine();

        if ($failures !== []) {
            $io->writeln('<error>No eligible permutation found for the following combos:</error>');
            foreach ($failures as $combo) {
                $io->writeln(sprintf(
                    '  - players=%d partners=%d repeat=%d fixedTeams=%s',
                    $combo['players'],
                    $combo['partners'],
                    $combo['repeat'],
                    $combo['fixedTeams'] ? 'true' : 'false'
                ));
            }
            $io->newLine();
        }

        $io->writeln(sprintf(
            '<comment>Generated %d combo%s into v%d/. Run `templates:metrics --templates-version=%d` to inspect.</comment>',
            $totalCombos,
            $totalCombos === 1 ? '' : 's',
            $writeVersion,
            $writeVersion
        ));

        return count($failures);
    }

    /**
     * @param  array{players:int,partners:int,repeat:int,courts:int,fixedTeams:bool}  $combo
     */
    private function liveTableCallback(
        ConsoleSectionOutput $counterSection,
        ConsoleSectionOutput $liveSection,
        array $combo,
        int $counterCurrent,
        int $counterTotal,
        bool $includeStatsColumns
    ): callable {
        $latestPairing = null;
        $latestMatchMaking = null;
        $latestOrdering = null;

        return function (GenerationProgress $event) use (
            $counterSection,
            $liveSection,
            $combo,
            $counterCurrent,
            $counterTotal,
            $includeStatsColumns,
            &$latestPairing,
            &$latestMatchMaking,
            &$latestOrdering
        ): void {
            if ($event instanceof PairingProgress) {
                $latestPairing = $event;
                if ($event->getTotalSeeds() > 1) {
                    $counterSection->overwrite(sprintf(
                        '<info>[%d/%d]</info> pairing seed %d/%d (in progress)',
                        $counterCurrent,
                        $counterTotal,
                        $event->getCurrentSeed(),
                        $event->getTotalSeeds()
                    ));
                }
            } elseif ($event instanceof MatchMakingProgress) {
                $latestMatchMaking = $event;
                if ($event->getTotalSeeds() > 1) {
                    $counterSection->overwrite(sprintf(
                        '<info>[%d/%d]</info> match-making seed %d/%d (in progress)',
                        $counterCurrent,
                        $counterTotal,
                        $event->getCurrentSeed(),
                        $event->getTotalSeeds()
                    ));
                }
            } elseif ($event instanceof OrderingProgress) {
                $latestOrdering = $event;
            }

            $snapshot = TemplateMatches::fromProgress(
                $combo['players'],
                $combo['partners'],
                $combo['repeat'],
                $combo['courts'],
                $combo['fixedTeams'],
                $latestPairing,
                $latestMatchMaking,
                $latestOrdering
            );

            $liveSection->clear();
            $this->renderLiveSnapshotTable($liveSection, $snapshot, true, null, $includeStatsColumns);
        };
    }

    private function bufferedFallbackCallback(OutputInterface $output): callable
    {
        return function (GenerationProgress $event) use ($output): void {
            if (! $event->isFinal()) {
                return;
            }
            if ($event instanceof PairingProgress) {
                $seedTag = $event->getTotalSeeds() > 1
                    ? sprintf(' seed %d/%d |', $event->getCurrentSeed(), $event->getTotalSeeds())
                    : '';
                $output->writeln(sprintf(
                    '  pairing done |%s nodes %s | best minBal=%s avgBal=%s',
                    $seedTag,
                    number_format($event->getNodesExplored(), 0, '.', ','),
                    $event->getBestMinPartnersFairness() === null ? '-' : number_format($event->getBestMinPartnersFairness(), 4),
                    $event->getBestAvgPartnersFairness() === null ? '-' : number_format($event->getBestAvgPartnersFairness(), 4)
                ));
            } elseif ($event instanceof MatchMakingProgress) {
                $seedTag = $event->getTotalSeeds() > 1
                    ? sprintf(' seed %d/%d |', $event->getCurrentSeed(), $event->getTotalSeeds())
                    : '';
                $output->writeln(sprintf(
                    '  match-making done |%s iter %s | templates %s | best variation %s',
                    $seedTag,
                    number_format($event->getIterations(), 0, '.', ','),
                    number_format($event->getTemplatesGenerated(), 0, '.', ','),
                    $event->getBestMeetingsVariation() === null
                        ? '-'
                        : number_format($event->getBestMeetingsVariation(), 4)
                ));
            } elseif ($event instanceof OrderingProgress) {
                $output->writeln(sprintf(
                    '  ordering done [%s] | iter %s | best min=%s avg=%s',
                    TemplateCombinations::stopReasonLabel($event->getStopReason()) ?? '-',
                    number_format($event->getIterations(), 0, '.', ','),
                    $event->getBestMin() === null ? '-' : number_format($event->getBestMin(), 4),
                    $event->getBestAvg() === null ? '-' : number_format($event->getBestAvg(), 4)
                ));
            }
        };
    }
}

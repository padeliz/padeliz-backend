<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\MetricsFormatterTrait;
use App\Console\TemplateComboResolver;
use App\Services\Schedule\Exception\TemplateMatchesNotFoundException;
use App\Services\Schedule\TemplateCombinations;
use App\Services\Schedule\TemplateMatchesRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\TableSeparator;

/**
 * Read-only metrics over committed schedule templates.
 *
 * Defaults to mixed templates. Use --fixed-teams=1 for fixed-only, or
 * --include-fixed-teams to list both mixed and fixed JSON files.
 */
final class TemplatesMetricsCommand extends Command
{
    use MetricsFormatterTrait;

    protected $signature = 'templates:metrics
        {--combinations= : Space-separated "players:partners,..." pairs}
        {--templates-version= : Templates version directory to read (required)}
        {--with-stats : Include stats columns in the output table}
        {--players= : Filter by players count}
        {--partners= : Filter by opponents per player}
        {--repeat= : Filter by repeat opponents (default 1)}
        {--fixed-teams= : Filter by fixed teams (0 or 1; default mixed only)}
        {--include-fixed-teams : Include both mixed and fixed-teams JSON}
        {--courts= : Filter by court count (default 1)}';

    protected $description = 'Read-only metrics over committed schedule templates (mixed by default)';

    private TemplateMatchesRepository $repository;

    public function __construct(?TemplateMatchesRepository $repository = null)
    {
        parent::__construct();
        $this->repository = $repository ?? new TemplateMatchesRepository();
    }

    public function handle(): int
    {
        $combinations = $this->parseMetricsCombinations($this->option('combinations'))
            ?? TemplateCombinations::COMBINATIONS;

        try {
            $version = $this->parseRequiredTemplateVersion($this->option('templates-version'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $includeFixedTeams = (bool) $this->option('include-fixed-teams');
        $fixedTeamsOption = $this->option('fixed-teams');

        if ($includeFixedTeams && $fixedTeamsOption !== null && $fixedTeamsOption !== '') {
            $this->error('Use either --fixed-teams or --include-fixed-teams, not both.');

            return self::FAILURE;
        }

        $filteredDiscovery = $this->hasMetricsComboFilters($this->input) || $includeFixedTeams;
        $includeStatsColumns = (bool) $this->option('with-stats');

        try {
            if ($includeFixedTeams) {
                $combos = $this->resolveIncludeFixedTeamsCombos($version, $combinations);
            } else {
                $defaultFixedTeams = false;
                if ($fixedTeamsOption !== null && $fixedTeamsOption !== '') {
                    $defaultFixedTeams = (new TemplateComboResolver())->parseFilters($this->input)['fixedTeams'] ?? false;
                }
                $combos = $this->resolveMetricsCombos(
                    $this->input,
                    $this->repository,
                    $version,
                    $combinations,
                    $defaultFixedTeams
                );
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $latestVersion = null;

        try {
            $latestVersion = $this->repository->latestVersion();
        } catch (\RuntimeException) {
            // Empty base dirs may have no v{N}/ yet.
        }

        if ($latestVersion === null) {
            $versionLabel = sprintf('v%d', $version);
        } elseif ($version === $latestVersion) {
            $versionLabel = sprintf('v%d <comment>(latest)</comment>', $version);
        } else {
            $versionLabel = sprintf('v%d <comment>(latest: v%d)</comment>', $version, $latestVersion);
        }

        if ($combos === []) {
            $this->line(sprintf(
                '<info>Reading:</info> %s   <info>Base dir:</info> %s   <info>Combos:</info> 0',
                $versionLabel,
                $this->repository->getBaseDir()
            ));
            $this->warn(sprintf('No template files match the provided filters under v%d.', $version));

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '<info>Reading:</info> %s   <info>Base dir:</info> %s   <info>Combos:</info> %d',
            $versionLabel,
            $this->repository->getBaseDir(),
            count($combos)
        ));
        $this->newLine();

        $loadedTemplates = [];
        $renderRows = [];
        $missing = 0;

        foreach ($combos as $combo) {
            try {
                $template = $this->repository->findAt(
                    $version,
                    $combo['players'],
                    $combo['partners'],
                    $combo['repeat'],
                    $combo['courts'],
                    $combo['fixedTeams']
                );
                $loadedTemplates[] = $template;
                $renderRows[] = ['combo' => $combo, 'template' => $template];
            } catch (TemplateMatchesNotFoundException) {
                if (! $filteredDiscovery) {
                    $missing++;
                    $loadedTemplates[] = null;
                    $renderRows[] = ['combo' => $combo, 'template' => null];
                }
            }
        }

        $layout = $this->resolveUnifiedTableLayout($combos, $loadedTemplates, $includeStatsColumns);
        $table = $this->makeUnifiedTable($this->output);
        $table->setHeaders($this->unifiedHeaders($layout));
        $this->writeTableContextLine($this->output, $layout);
        $this->newLine();

        $minPartnersFairs = [];
        $avgPartnersFairs = [];
        $partnersVars = [];
        $meetingsVars = [];
        $minPlayingFairs = [];
        $avgPlayingFairs = [];
        $maxPlayingPenalties = [];
        $mins = [];
        $avgs = [];
        $previousPlayers = null;

        foreach ($renderRows as $row) {
            $combo = $row['combo'];
            $template = $row['template'];
            $firstOfGroup = $previousPlayers !== $combo['players'];
            if ($firstOfGroup && $previousPlayers !== null) {
                $table->addRow(array_fill(0, $layout['totalColumns'], new TableSeparator()));
            }

            if ($template !== null) {
                $minPartnersFairs[] = $template->getPairingQualityMinPartnersFairness();
                $avgPartnersFairs[] = $template->getPairingQualityAvgPartnersFairness();
                $partnersVars[] = $template->getPairingQualityPartnersCountVariation();
                $meetingsVars[] = $template->getMatchMakingQualityMeetingsVariation();
                $minPlayingFairs[] = $template->getMatchMakingQualityMinPlayingFairness();
                $avgPlayingFairs[] = $template->getMatchMakingQualityAvgPlayingFairness();
                $maxPlayingPenalties[] = $template->getMatchMakingQualityMaxPlayingFairnessPenalty();
                $mins[] = $template->getOrderingQualityMinDistribution();
                $avgs[] = $template->getOrderingQualityAvgDistribution();
            }

            $table->addRow($this->buildUnifiedRow(
                $template,
                $combo['players'],
                $combo['partners'],
                $firstOfGroup,
                $layout
            ));

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

        $table->render();

        if ($missing > 0) {
            $this->error(sprintf('%d template(s) missing under v%d.', $missing, $version));
        }

        return $missing > 0 ? $missing : self::SUCCESS;
    }

    /**
     * @param  array<int, list<int>>  $combinations
     * @return list<array{players:int,partners:int,repeat:int,courts:int,fixedTeams:bool}>
     */
    private function resolveIncludeFixedTeamsCombos(int $version, array $combinations): array
    {
        $resolver = new TemplateComboResolver();
        $filters = $resolver->parseFilters($this->input);
        unset($filters['fixedTeams']);

        $customCombinations = $this->parseMetricsCombinations($this->option('combinations'));
        $discoveryFilters = array_merge([
            'repeat' => 1,
            'courts' => 1,
        ], $filters);
        if ($customCombinations !== null) {
            $discoveryFilters['playersPartners'] = $customCombinations;
        } elseif (! $this->hasMetricsComboFilters($this->input)) {
            $discoveryFilters['playersPartners'] = $combinations;
        }

        return $this->repository->listComboIdentitiesAt($version, $discoveryFilters);
    }
}

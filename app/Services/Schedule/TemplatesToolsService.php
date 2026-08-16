<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Services\Schedule\Exception\TemplateMatchesNotFoundException;

/**
 * Manager tools helpers: template catalog, demo payload, and quality metrics summary.
 *
 * Mirrors {@see \App\Console\Commands\TemplatesMetricsCommand} combo resolution
 * (mixed by default; optional include-fixed-teams) as structured JSON for the API.
 */
final class TemplatesToolsService
{
    public function __construct(
        private readonly TemplateMatchesRepository $repository = new TemplateMatchesRepository(),
    ) {
    }

    public function repository(): TemplateMatchesRepository
    {
        return $this->repository;
    }

    /**
     * @return array{
     *     version: int,
     *     latest_version: int,
     *     include_fixed_teams: bool,
     *     source_directory: string,
     *     combos: list<array{
     *         players: int,
     *         partners: int,
     *         repeat: int,
     *         courts: int,
     *         fixed_teams: bool,
     *         present: bool,
     *         in_expected_catalog: bool,
     *         eligible: null|bool,
     *         usable: null|bool
     *     }>
     * }
     */
    public function catalog(bool $includeFixedTeams, ?int $version = null): array
    {
        $latestVersion = $this->repository->latestVersion();
        $version ??= $latestVersion;

        $onDisk = $this->listOnDiskCombos($version, $includeFixedTeams);
        $expected = $this->expectedCatalogCombos();

        $byKey = [];
        foreach ($expected as $combo) {
            $key = $this->comboKey($combo);
            $byKey[$key] = [
                'players' => $combo['players'],
                'partners' => $combo['partners'],
                'repeat' => $combo['repeat'],
                'courts' => $combo['courts'],
                'fixed_teams' => $combo['fixedTeams'],
                'present' => false,
                'in_expected_catalog' => true,
                'eligible' => null,
                'usable' => null,
            ];
        }

        foreach ($onDisk as $combo) {
            $key = $this->comboKey($combo);
            $status = $this->loadStatus(
                $version,
                $combo['players'],
                $combo['partners'],
                $combo['repeat'],
                $combo['courts'],
                $combo['fixedTeams']
            );

            if (isset($byKey[$key])) {
                $byKey[$key]['present'] = true;
                $byKey[$key]['eligible'] = $status['eligible'];
                $byKey[$key]['usable'] = $status['usable'];
            } else {
                $byKey[$key] = [
                    'players' => $combo['players'],
                    'partners' => $combo['partners'],
                    'repeat' => $combo['repeat'],
                    'courts' => $combo['courts'],
                    'fixed_teams' => $combo['fixedTeams'],
                    'present' => true,
                    'in_expected_catalog' => false,
                    'eligible' => $status['eligible'],
                    'usable' => $status['usable'],
                ];
            }
        }

        $combos = array_values($byKey);
        usort(
            $combos,
            static fn (array $a, array $b): int => [
                $a['players'],
                $a['partners'],
                $a['repeat'],
                $a['courts'],
                (int) $a['fixed_teams'],
            ] <=> [
                $b['players'],
                $b['partners'],
                $b['repeat'],
                $b['courts'],
                (int) $b['fixed_teams'],
            ]
        );

        return [
            'version' => $version,
            'latest_version' => $latestVersion,
            'include_fixed_teams' => $includeFixedTeams,
            'source_directory' => 'v' . $version,
            'combos' => $combos,
        ];
    }

    /**
     * @throws TemplateMatchesNotFoundException
     * @return array<string, mixed>
     */
    public function demo(
        int $players,
        int $partners,
        int $repeat,
        int $courts,
        bool $fixedTeams = false,
        ?int $version = null
    ): array {
        $version ??= $this->repository->latestVersion();
        $template = $this->repository->findAt($version, $players, $partners, $repeat, $courts, $fixedTeams);
        $payload = $template->toArray();
        $payload['version'] = $version;
        $payload['eligible'] = $template->isEligible();
        $payload['usable'] = $template->isUsable();

        return $payload;
    }

    /**
     * @return array{
     *     version: int,
     *     latest_version: int,
     *     include_fixed_teams: bool,
     *     missing_count: int,
     *     rows: list<array<string, mixed>>,
     *     averages: array<string, null|float>
     * }
     */
    public function metrics(bool $includeFixedTeams, ?int $version = null): array
    {
        $latestVersion = $this->repository->latestVersion();
        $version ??= $latestVersion;

        $combos = $includeFixedTeams
            ? $this->listOnDiskCombos($version, true)
            : $this->expectedCatalogCombos();

        $rows = [];
        $missing = 0;

        $minPartnersFairs = [];
        $avgPartnersFairs = [];
        $partnersVars = [];
        $meetingsVars = [];
        $minPlayingFairs = [];
        $avgPlayingFairs = [];
        $maxPlayingPenalties = [];
        $mins = [];
        $avgs = [];

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
            } catch (TemplateMatchesNotFoundException) {
                if ($includeFixedTeams) {
                    continue;
                }
                $missing++;
                $rows[] = [
                    'players' => $combo['players'],
                    'partners' => $combo['partners'],
                    'repeat' => $combo['repeat'],
                    'courts' => $combo['courts'],
                    'fixed_teams' => $combo['fixedTeams'],
                    'present' => false,
                    'eligible' => null,
                    'usable' => null,
                    'metrics' => null,
                ];

                continue;
            }

            $minPartnersFairs[] = $template->getPairingQualityMinPartnersFairness();
            $avgPartnersFairs[] = $template->getPairingQualityAvgPartnersFairness();
            $partnersVars[] = $template->getPairingQualityPartnersCountVariation();
            $meetingsVars[] = $template->getMatchMakingQualityMeetingsVariation();
            $minPlayingFairs[] = $template->getMatchMakingQualityMinPlayingFairness();
            $avgPlayingFairs[] = $template->getMatchMakingQualityAvgPlayingFairness();
            $maxPlayingPenalties[] = $template->getMatchMakingQualityMaxPlayingFairnessPenalty();
            $mins[] = $template->getOrderingQualityMinDistribution();
            $avgs[] = $template->getOrderingQualityAvgDistribution();

            $rows[] = [
                'players' => $combo['players'],
                'partners' => $combo['partners'],
                'repeat' => $combo['repeat'],
                'courts' => $combo['courts'],
                'fixed_teams' => $combo['fixedTeams'],
                'present' => true,
                'eligible' => $template->isEligible(),
                'usable' => $template->isUsable(),
                'metrics' => $template->toArray()['metrics'],
            ];
        }

        return [
            'version' => $version,
            'latest_version' => $latestVersion,
            'include_fixed_teams' => $includeFixedTeams,
            'missing_count' => $missing,
            'rows' => $rows,
            'averages' => [
                'min_partners_fairness' => $this->avgNullableFloats($minPartnersFairs),
                'avg_partners_fairness' => $this->avgNullableFloats($avgPartnersFairs),
                'partners_count_variation' => $this->avgNullableInts($partnersVars),
                'meetings_variation' => $this->avgNullableFloats($meetingsVars),
                'min_playing_fairness' => $this->avgNullableFloats($minPlayingFairs),
                'avg_playing_fairness' => $this->avgNullableFloats($avgPlayingFairs),
                'max_playing_fairness_penalty' => $this->avgNullableFloats($maxPlayingPenalties),
                'min_distribution' => $this->avgNullableFloats($mins),
                'avg_distribution' => $this->avgNullableFloats($avgs),
            ],
        ];
    }

    /**
     * @return list<array{players:int,partners:int,repeat:int,courts:int,fixedTeams:bool}>
     */
    private function expectedCatalogCombos(): array
    {
        $combos = [];
        foreach (TemplateCombinations::COMBINATIONS as $players => $partnersList) {
            foreach ($partnersList as $partners) {
                $combos[] = [
                    'players' => (int) $players,
                    'partners' => (int) $partners,
                    'repeat' => 1,
                    'courts' => 1,
                    'fixedTeams' => false,
                ];
            }
        }

        return $combos;
    }

    /**
     * @return list<array{players:int,partners:int,repeat:int,courts:int,fixedTeams:bool}>
     */
    private function listOnDiskCombos(int $version, bool $includeFixedTeams): array
    {
        $filters = [];
        if (! $includeFixedTeams) {
            $filters['fixedTeams'] = false;
        }

        return $this->repository->listComboIdentitiesAt($version, $filters);
    }

    /**
     * @param array{players:int,partners:int,repeat:int,courts:int,fixedTeams:bool} $combo
     */
    private function comboKey(array $combo): string
    {
        return sprintf(
            '%d:%d:%d:%d:%d',
            $combo['players'],
            $combo['partners'],
            $combo['repeat'],
            $combo['courts'],
            $combo['fixedTeams'] ? 1 : 0
        );
    }

    /**
     * @return array{eligible: null|bool, usable: null|bool}
     */
    private function loadStatus(
        int $version,
        int $players,
        int $partners,
        int $repeat,
        int $courts,
        bool $fixedTeams
    ): array {
        try {
            $template = $this->repository->findAt($version, $players, $partners, $repeat, $courts, $fixedTeams);
        } catch (TemplateMatchesNotFoundException|\Throwable) {
            return ['eligible' => null, 'usable' => null];
        }

        return [
            'eligible' => $template->isEligible(),
            'usable' => $template->isUsable(),
        ];
    }

    /**
     * @param list<null|float> $values
     */
    private function avgNullableFloats(array $values): ?float
    {
        $filtered = array_values(array_filter($values, static fn ($v) => $v !== null));
        if ($filtered === []) {
            return null;
        }

        return array_sum($filtered) / count($filtered);
    }

    /**
     * @param list<null|int> $values
     */
    private function avgNullableInts(array $values): ?float
    {
        $filtered = array_values(array_filter($values, static fn ($v) => $v !== null));
        if ($filtered === []) {
            return null;
        }

        return array_sum($filtered) / count($filtered);
    }
}

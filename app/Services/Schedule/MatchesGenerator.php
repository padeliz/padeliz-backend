<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use RuntimeException;

/**
 * Loads a mixed template and remaps player indices to real player ids, with time slots.
 *
 * Schedule generation always uses mixed templates (never -fixedteams).
 */
final class MatchesGenerator
{
    /** @var array<int, array<int, array<int, array<int, int>|string>>> */
    private array $matchesByCourt;

    private int $roundsTotal;

    /** @var array<int|string, int> */
    private array $partnersCount;

    /** @var array<int|string, array<int|string, int>> */
    private array $playersMet;

    private int $partnersCountVariation;

    private int $templateVersion;

    /**
     * @param  list<int>  $playerIds  Ordered player ids (template index 0 → first id).
     */
    public function __construct(
        array $playerIds,
        int $opponentsPerPlayer,
        int $repeatPartners,
        int $courts,
        string $timeStart,
        string $timeEnd,
        bool $includeFinal,
        bool $hasDemonstrativeMatch = false,
        ?int $templateVersion = null,
        ?TemplateMatchesRepository $repository = null,
    ) {
        $repository ??= new TemplateMatchesRepository();
        $this->templateVersion = $templateVersion ?? $repository->latestVersion();

        $templateMatches = $repository->findAt(
            $this->templateVersion,
            count($playerIds),
            $opponentsPerPlayer,
            $repeatPartners,
            $courts,
            false
        );

        if (! $templateMatches->isEligible() || ! TemplateMatches::hasValidRoundSchedule($templateMatches->getMatches())) {
            throw new RuntimeException(sprintf(
                'Template for %d players, %d opponents, repeat %d, %d courts is missing or has no valid sorted schedule.',
                count($playerIds),
                $opponentsPerPlayer,
                $repeatPartners,
                $courts
            ));
        }

        $matchesByCourt = $templateMatches->getMatches() ?? [];
        array_walk_recursive($matchesByCourt, static function (&$playerIndex) use ($playerIds): void {
            if (is_int($playerIndex)) {
                $playerIndex = $playerIds[$playerIndex];
            }
        });

        $this->roundsTotal = 0;
        foreach ($matchesByCourt as $courtRounds) {
            $this->roundsTotal = max($this->roundsTotal, count($courtRounds));
        }

        $slots = RoundClockCalculator::slots(
            $timeStart,
            $timeEnd,
            $this->roundsTotal,
            $includeFinal,
            $hasDemonstrativeMatch,
        );

        foreach ($matchesByCourt as $courtIdx => &$courtRounds) {
            foreach ($courtRounds as $r => &$round) {
                if (! isset($slots[$r])) {
                    continue;
                }
                $round[2] = $slots[$r]['start'];
                $round[3] = $slots[$r]['end'];
            }
            unset($round);
        }
        unset($courtRounds);

        $countPartners = [];
        $templateCountPartners = $templateMatches->getPairingQualityPartnersCount() ?? [];
        foreach ($templateCountPartners as $key => $value) {
            $countPartners[$playerIds[(int) $key]] = (int) $value;
        }

        $countPlayersMet = [];
        $templateCountPlayersMet = $templateMatches->getMatchMakingQualityPlayersMet() ?? [];
        foreach ($templateCountPlayersMet as $key => $value) {
            $mapped = [];
            foreach ($value as $opponent => $count) {
                $mapped[$playerIds[(int) $opponent]] = (int) $count;
            }
            $countPlayersMet[$playerIds[(int) $key]] = $mapped;
        }

        $this->matchesByCourt = $matchesByCourt;
        $this->partnersCountVariation = (int) $templateMatches->getPairingQualityPartnersCountVariation();
        $this->partnersCount = $countPartners;
        $this->playersMet = $countPlayersMet;
    }

    /**
     * @return array<int, array<int, array<int, array<int, int>|string>>>
     */
    public function getMatches(): array
    {
        return $this->matchesByCourt;
    }

    public function getRoundsTotal(): int
    {
        return $this->roundsTotal;
    }

    public function getMatchesCount(): int
    {
        $total = 0;
        foreach ($this->matchesByCourt as $courtRounds) {
            $total += count($courtRounds);
        }

        return $total;
    }

    /**
     * @return array<int|string, int>
     */
    public function getPartnersCount(): array
    {
        return $this->partnersCount;
    }

    public function getPartnersCountBy(int|string $player): int
    {
        return $this->partnersCount[$player];
    }

    /**
     * @return array<int|string, array<int|string, int>>
     */
    public function getPlayersMet(): array
    {
        return $this->playersMet;
    }

    /**
     * @return array<int|string, int>
     */
    public function getPlayersMetBy(int|string $player): array
    {
        return $this->playersMet[$player] ?? [];
    }

    public function partnersCountVariation(): int
    {
        return $this->partnersCountVariation;
    }

    public function getTemplateVersion(): int
    {
        return $this->templateVersion;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\PlayerLevel;

use App\Models\EventDivision;
use App\Models\Level;
use App\Models\PlayerLevel;
use Illuminate\Validation\ValidationException;

final class PlayerLevelService
{
    /**
     * Levels carry exactly two decimals so they always land on a seeded band boundary.
     */
    public const LEVEL_PATTERN = '/^\d+\.\d{2}$/';

    public function currentLevel(int $playerId): ?PlayerLevel
    {
        return PlayerLevel::query()
            ->where('player_user_id', $playerId)
            ->orderByDesc('inserted_at')
            ->orderByDesc('id_player_level')
            ->first();
    }

    public function upsertForAssessment(int $playerId, int $assessmentId, float|string $level): PlayerLevel
    {
        return $this->upsert($playerId, $level, $assessmentId, null);
    }

    public function upsertForParticipation(int $playerId, int $participationId, float|string $level): PlayerLevel
    {
        return $this->upsert($playerId, $level, null, $participationId);
    }

    /**
     * Normalizes to two decimals and rejects anything outside the seeded bands.
     */
    public function normalizeLevel(float|string $level): string
    {
        $raw = is_string($level) ? trim($level) : number_format($level, 2, '.', '');

        if (preg_match(self::LEVEL_PATTERN, $raw) !== 1) {
            throw ValidationException::withMessages([
                'level' => ['The level must have exactly 2 decimals.'],
            ]);
        }

        if ($this->bandFor($raw) === null) {
            throw ValidationException::withMessages([
                'level' => ["The level {$raw} is not inside a seeded level band."],
            ]);
        }

        return $raw;
    }

    /**
     * A player level is sourced by an approved assessment or by a participation, never both.
     */
    public function assertSingleSource(?int $assessmentId, ?int $participationId): void
    {
        if (($assessmentId === null) === ($participationId === null)) {
            throw ValidationException::withMessages([
                'source' => ['A player level comes from exactly one of an assessment or a participation.'],
            ]);
        }
    }

    /**
     * Bounds run weakest (`level_min`) to strongest (`level_max`) on the inverted scale, so a level
     * fits when `level_max <= value <= level_min`. A division is only constrained when both
     * bounds are set.
     */
    public static function levelFitsBounds(float|string $level, float|string|null $levelMin, float|string|null $levelMax): bool
    {
        if ($levelMin === null || $levelMax === null) {
            return true;
        }

        $value = (float) $level;

        return $value <= (float) $levelMin && $value >= (float) $levelMax;
    }

    /**
     * Event-division eligibility for a player: needs any player level, then the bounds rule.
     */
    public function isEligibleForEventDivision(int $playerId, EventDivision $division): bool
    {
        $current = $this->currentLevel($playerId);

        if ($current === null) {
            return false;
        }

        return self::levelFitsBounds((string) $current->level, $division->level_min, $division->level_max);
    }

    public function bandFor(float|string $level): ?Level
    {
        return Level::query()
            ->where('level_min', '>=', (float) $level)
            ->where('level_max', '<=', (float) $level)
            ->orderBy('level_max')
            ->first();
    }

    private function upsert(
        int $playerId,
        float|string $level,
        ?int $assessmentId,
        ?int $participationId,
    ): PlayerLevel {
        $this->assertSingleSource($assessmentId, $participationId);

        $normalized = $this->normalizeLevel($level);

        $attributes = $assessmentId !== null
            ? ['player_level_assessment_id' => $assessmentId]
            : ['event_participation_id' => $participationId];

        $playerLevel = PlayerLevel::query()->where($attributes)->first();

        if ($playerLevel === null) {
            return PlayerLevel::query()->create([
                'player_user_id' => $playerId,
                'level' => $normalized,
                'player_level_assessment_id' => $assessmentId,
                'event_participation_id' => $participationId,
            ]);
        }

        $playerLevel->fill([
            'player_user_id' => $playerId,
            'level' => $normalized,
        ])->save();

        return $playerLevel;
    }
}

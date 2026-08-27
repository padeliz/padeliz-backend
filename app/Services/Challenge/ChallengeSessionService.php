<?php

declare(strict_types=1);

namespace App\Services\Challenge;

use App\Enums\Challenge\ChallengeSessionStatus;
use App\Enums\Challenge\ChallengeSessionType;
use App\Models\Challenge\ChallengeSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ChallengeSessionService
{
    public function create(ChallengeSessionType $type): ChallengeSession
    {
        if ($this->hasActiveSessionOfType($type)) {
            throw new ChallengeStateException('An active session of this type already exists.');
        }

        $title = $type === ChallengeSessionType::KingOfTheCourt
            ? 'King of the Court – ' . now()->format('Y-m-d H:i')
            : 'Smash Contest – ' . now()->format('Y-m-d H:i');

        return ChallengeSession::query()->create([
            'type' => $type,
            'status' => ChallengeSessionStatus::Active,
            'title' => $title,
            'bottom_text' => $title,
            'started_at' => time(),
            'completed_at' => null,
            'state_json' => $this->emptyState($type),
            'state_sequence' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ChallengeSession::query()->orderByDesc('started_at');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function hasActiveSessionOfType(ChallengeSessionType $type): bool
    {
        return ChallengeSession::query()
            ->where('type', $type)
            ->where('status', ChallengeSessionStatus::Active)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyState(ChallengeSessionType $type): array
    {
        if ($type === ChallengeSessionType::KingOfTheCourt) {
            return [
                'can_manual_jump_in' => true,
                'can_complete' => true,
                'complete_blocked_reasons' => [],
                'court_count' => 0,
                'waiting' => [],
                'court' => [
                    'team_a' => null,
                    'team_b' => null,
                ],
                'leaderboard' => [],
            ];
        }

        return [
            'can_manual_jump_in' => true,
            'can_complete' => true,
            'complete_blocked_reasons' => [],
            'court_count' => 0,
            'waiting' => [],
            'court' => null,
            'leaderboard' => [],
        ];
    }
}

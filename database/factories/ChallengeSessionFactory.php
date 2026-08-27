<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Challenge\ChallengeSessionStatus;
use App\Enums\Challenge\ChallengeSessionType;
use App\Models\Challenge\ChallengeSession;
use App\Services\Challenge\ChallengeSessionService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeSessionFactory extends Factory
{
    protected $model = ChallengeSession::class;

    public function definition(): array
    {
        $type = ChallengeSessionType::KingOfTheCourt;

        return [
            'type' => $type,
            'status' => ChallengeSessionStatus::Active,
            'title' => 'King of the Court – Test',
            'bottom_text' => 'King of the Court – Test',
            'started_at' => time(),
            'completed_at' => null,
            'state_json' => app(ChallengeSessionService::class)->emptyState($type),
            'state_sequence' => 0,
            'inserted_at' => time(),
        ];
    }

    public function kingOfTheCourt(): static
    {
        return $this->state(fn () => [
            'type' => ChallengeSessionType::KingOfTheCourt,
            'title' => 'King of the Court – Test',
            'bottom_text' => 'King of the Court – Test',
            'state_json' => app(ChallengeSessionService::class)->emptyState(ChallengeSessionType::KingOfTheCourt),
        ]);
    }

    public function smashContest(): static
    {
        return $this->state(fn () => [
            'type' => ChallengeSessionType::SmashContest,
            'title' => 'Smash Contest – Test',
            'bottom_text' => 'Smash Contest – Test',
            'state_json' => app(ChallengeSessionService::class)->emptyState(ChallengeSessionType::SmashContest),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ChallengeSessionStatus::Completed,
            'completed_at' => time(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventParticipation;
use App\Models\Player;
use App\Models\PlayerLevel;
use App\Models\PlayerLevelAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerLevel>
 */
class PlayerLevelFactory extends Factory
{
    protected $model = PlayerLevel::class;

    public function definition(): array
    {
        $player = Player::factory();

        return [
            'player_id' => $player,
            'level' => sprintf('%d.%02d', $this->faker->numberBetween(1, 5), $this->faker->numberBetween(0, 99)),
            'player_level_assessment_id' => PlayerLevelAssessment::factory()->approved()->state([
                'player_id' => $player,
            ]),
            'event_participation_id' => null,
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }

    public function fromAssessment(PlayerLevelAssessment $assessment): static
    {
        return $this->state(fn (): array => [
            'player_id' => $assessment->player_id,
            'player_level_assessment_id' => $assessment->id_player_level_assessment,
            'event_participation_id' => null,
        ]);
    }

    public function fromParticipation(EventParticipation $participation): static
    {
        return $this->state(fn (): array => [
            'player_id' => $participation->player_id,
            'player_level_assessment_id' => null,
            'event_participation_id' => $participation->id_participation,
        ]);
    }

    public function level(string $level): static
    {
        return $this->state(fn (): array => [
            'level' => $level,
        ]);
    }
}

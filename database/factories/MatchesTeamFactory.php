<?php

namespace Database\Factories;

use App\Models\MatchesTeam;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchesTeamFactory extends Factory
{
    protected $model = MatchesTeam::class;

    public function definition()
    {
        return [
            'player_1' => Player::factory()->create()->id_player,
            'player_2' => Player::factory()->create()->id_player,
            'inserted_at' => $this->faker->unixTime(),
            'updated_at' => $this->faker->optional()->unixTime(),
        ];
    }
}

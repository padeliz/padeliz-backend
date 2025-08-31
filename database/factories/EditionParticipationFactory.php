<?php

namespace Database\Factories;

use App\Models\EditionParticipation;
use App\Models\Edition;
use App\Models\Player;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionParticipationFactory extends Factory
{
    protected $model = EditionParticipation::class;

    public function definition()
    {
        return [
            'edition_id' => Edition::factory(),
            'player_id' => Player::factory(),
            'division_id' => Division::factory()->create()->id_division,
            'is_minor' => $this->faker->boolean(20),
            'is_student' => $this->faker->boolean(30),
            'discount' => $this->faker->optional()->numberBetween(5, 20),
            'payment' => $this->faker->numberBetween(35, 55),
            'absence_reason' => $this->faker->optional()->randomElement(['SICK', 'FORGOT', 'EXAMS', 'RECONSIDERED']),
            'ban_reason' => $this->faker->optional()->randomElement(['LEFT SUDDENLY', 'AGGRESSIVE PLAYER', 'LIBIDINOUS', '']),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}

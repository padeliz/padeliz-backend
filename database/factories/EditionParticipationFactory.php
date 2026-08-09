<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionParticipationFactory extends Factory
{
    protected $model = EditionParticipation::class;

    public function definition()
    {
        $editionDivision = EditionDivision::factory()->create();

        return [
            'edition_id' => $editionDivision->edition_id,
            'player_id' => Player::factory(),
            'edition_division_id' => $editionDivision->id_edition_division,
            'is_minor' => $this->faker->boolean(20),
            'is_student' => $this->faker->boolean(30),
            'discount' => $this->faker->optional()->numberBetween(5, 20),
            'payment' => $this->faker->numberBetween(35, 55),
            'absence_reason' => $this->faker->optional()->randomElement(['SICK', 'FORGOT', 'EXAMS', 'RECONSIDERED']),
            'ban_reason' => $this->faker->optional()->randomElement(['LEFT SUDDENLY', 'AGGRESSIVE PLAYER', 'LIBIDINOUS', '']),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function forEdition(Edition $edition): static
    {
        return $this->state(fn () => [
            'edition_id' => $edition->id_edition,
        ]);
    }
}

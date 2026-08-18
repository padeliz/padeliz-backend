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
            'sort_order' => 0,
            'is_collecting_points' => true,
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

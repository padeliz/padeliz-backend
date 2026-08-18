<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionRegistration;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionRegistrationFactory extends Factory
{
    protected $model = EditionRegistration::class;

    public function definition()
    {
        $editionDivision = EditionDivision::factory()->create();

        return [
            'edition_id' => $editionDivision->edition_id,
            'player_id' => Player::factory(),
            'edition_division_id' => $editionDivision->id_edition_division,
            'is_minor' => false,
            'is_student' => $this->faker->boolean(30),
            'discount' => 0,
            'payment' => $this->faker->numberBetween(35, 55),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function forEdition(Edition $edition): static
    {
        return $this->state(fn () => [
            'edition_id' => $edition->id_edition,
        ]);
    }

    public function withoutEditionDivision(): static
    {
        return $this->state(fn () => [
            'edition_division_id' => null,
        ]);
    }
}

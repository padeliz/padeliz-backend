<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\GameMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameMatchFactory extends Factory
{
    protected $model = GameMatch::class;

    public function definition()
    {
        $editionDivision = EditionDivision::factory()->create();
        $editionDivisionCourt = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
        ]);

        return [
            'edition_division_id' => $editionDivision->id_edition_division,
            'edition_division_court_id' => $editionDivisionCourt->id_edition_division_court,
            'match_kind' => $this->faker->randomElement(['REGULAR', 'FINAL', 'DEMONSTRATIVE']),
            'sequence_no' => $this->faker->numberBetween(1, 20),
            'scheduled_start' => null,
            'scheduled_end' => null,
            'points_target' => $this->faker->optional()->numberBetween(10, 24),
            'inserted_at' => time(),
        ];
    }
}

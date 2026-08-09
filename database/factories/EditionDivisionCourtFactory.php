<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Court;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionDivisionCourtFactory extends Factory
{
    protected $model = EditionDivisionCourt::class;

    public function definition()
    {
        return [
            'edition_division_id' => EditionDivision::factory(),
            'court_id' => Court::factory(),
            'display_order' => $this->faker->numberBetween(0, 10),
            'inserted_at' => time(),
        ];
    }
}

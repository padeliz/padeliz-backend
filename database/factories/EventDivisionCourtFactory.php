<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Court;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventDivisionCourtFactory extends Factory
{
    protected $model = EventDivisionCourt::class;

    public function definition()
    {
        return [
            'event_division_id' => EventDivision::factory(),
            'court_id' => Court::factory(),
            'display_order' => $this->faker->numberBetween(0, 10),
            'inserted_at' => time(),
        ];
    }
}

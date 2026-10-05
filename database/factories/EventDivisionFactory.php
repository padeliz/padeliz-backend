<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Division;
use App\Models\PadelEvent;
use App\Models\EventDivision;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventDivisionFactory extends Factory
{
    protected $model = EventDivision::class;

    public function definition()
    {
        $division = Division::factory()->create();

        return [
            'event_id' => PadelEvent::factory(),
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => $this->faker->numberBetween(8, 16),
            'entry_fee' => 55,
            'skill_order' => 0,
            'playing_start_time' => Carbon::createFromTime($this->faker->numberBetween(12, 19), 30),
            'playing_end_time' => Carbon::createFromTime($this->faker->numberBetween(16, 21), 30),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}

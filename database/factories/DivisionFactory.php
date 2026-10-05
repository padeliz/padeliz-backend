<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition()
    {
        return [
            'name' => substr($this->faker->unique()->words(2, true), 0, 25),
            'color' => $this->faker->hexColor(),
            'its_first_event_id' => \App\Models\PadelEvent::factory()->create()->id_event,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}

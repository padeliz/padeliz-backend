<?php

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
            'is_students_only' => $this->faker->boolean(),
            'its_first_edition_id' => \App\Models\Edition::factory()->create()->id_edition,
        ];
    }
}

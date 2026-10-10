<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Level;
use App\Services\PlayerLevel\LevelBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    protected $model = Level::class;

    /**
     * The migration already seeds every band from 1.00 to 5.99, so the default builds a
     * synthetic band above that range to stay clear of the unique name index.
     */
    public function definition(): array
    {
        $division = $this->faker->unique()->numberBetween(6, 9);
        $segment = $this->faker->randomElement(['Low', 'Mid', 'High']);
        $bounds = [
            'Low' => ['99', '67'],
            'Mid' => ['66', '34'],
            'High' => ['33', '00'],
        ][$segment];

        return [
            'name' => sprintf('Division %d %s', $division, $segment),
            'level_min' => sprintf('%d.%s', $division, $bounds[0]),
            'level_max' => sprintf('%d.%s', $division, $bounds[1]),
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }

    public function band(LevelBand $band): static
    {
        return $this->state(fn (): array => [
            'name' => $band->name,
            'level_min' => $band->levelMin,
            'level_max' => $band->levelMax,
        ]);
    }
}

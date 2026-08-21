<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlayerCustomFieldOption>
 */
class PlayerCustomFieldOptionFactory extends Factory
{
    protected $model = PlayerCustomFieldOption::class;

    public function definition(): array
    {
        $label = $this->faker->unique()->word();

        return [
            'player_custom_field_id' => PlayerCustomField::factory()->select(),
            'code' => Str::slug($label) . '_' . $this->faker->unique()->numerify('##'),
            'label' => $label,
            'sort_order' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
            'inserted_at' => time(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}

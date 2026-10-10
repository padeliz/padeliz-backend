<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlayerCustomFieldType;
use App\Models\PlayerCustomField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlayerCustomField>
 */
class PlayerCustomFieldFactory extends Factory
{
    protected $model = PlayerCustomField::class;

    public function definition(): array
    {
        $label = $this->faker->unique()->words(3, true);

        return [
            'code' => Str::slug($label) . '_' . $this->faker->unique()->numerify('###'),
            'label' => substr($label, 0, 255),
            'help_text' => null,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'type' => PlayerCustomFieldType::Text,
            'rules_json' => null,
            'is_required' => false,
            'archived_at' => null,
            'inserted_at' => time(),
        ];
    }

    public function select(): static
    {
        return $this->state(fn (): array => [
            'type' => PlayerCustomFieldType::SingleSelect,
        ]);
    }

    public function multiSelect(): static
    {
        return $this->state(fn (): array => [
            'type' => PlayerCustomFieldType::MultiSelect,
        ]);
    }

    public function json(): static
    {
        return $this->state(fn (): array => [
            'type' => PlayerCustomFieldType::Json,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => time(),
        ]);
    }
}

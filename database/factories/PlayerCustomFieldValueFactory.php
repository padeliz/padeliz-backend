<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerCustomFieldValue>
 */
class PlayerCustomFieldValueFactory extends Factory
{
    protected $model = PlayerCustomFieldValue::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'player_custom_field_id' => PlayerCustomField::factory(),
            'edition_id' => null,
            'value_text' => $this->faker->sentence(3),
            'value_integer' => null,
            'value_boolean' => null,
            'value_date' => null,
            'submitted_by_player_id' => null,
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            'created_via_import_id' => null,
            'invalidated_at' => null,
            'invalidated_by_player_id' => null,
            'invalidation_reason' => null,
            'inserted_at' => time(),
        ];
    }
}

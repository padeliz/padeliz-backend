<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Models\User;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerCustomFieldValue>
 */
class PlayerCustomFieldValueFactory extends Factory
{
    protected $model = PlayerCustomFieldValue::class;

    public function pending(): static
    {
        return $this->state([
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_APP,
            'review_status' => PlayerCustomFieldValueReviewStatus::Pending,
            'reviewed_at' => null,
            'reviewed_by_manager_user_id' => null,
        ]);
    }

    public function definition(): array
    {
        $player = User::factory();

        return [
            'player_user_id' => $player,
            'player_custom_field_id' => PlayerCustomField::factory(),
            'submission_id' => PlayerCustomFieldSubmission::factory()->state([
                'player_user_id' => $player,
            ]),
            'value_text' => $this->faker->sentence(3),
            'value_integer' => null,
            'value_boolean' => null,
            'value_date' => null,
            'value_json' => null,
            'submitted_by_player_user_id' => null,
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            'created_via_import_id' => null,
            'review_status' => PlayerCustomFieldValueReviewStatus::Validated,
            'reviewed_at' => static fn (array $attributes): int => (int) $attributes['inserted_at'],
            'reviewed_by_manager_user_id' => null,
            'inserted_at' => time(),
        ];
    }
}

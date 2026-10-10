<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerCustomFieldSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerCustomFieldSubmission>
 */
class PlayerCustomFieldSubmissionFactory extends Factory
{
    protected $model = PlayerCustomFieldSubmission::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'submitted_via' => PlayerCustomFieldSubmission::SUBMITTED_VIA_APP,
            'submitted_by_player_id' => null,
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }

    public function viaManager(?int $submittedByPlayerId = null): static
    {
        return $this->state(fn (): array => [
            'submitted_via' => PlayerCustomFieldSubmission::SUBMITTED_VIA_MANAGER,
            'submitted_by_player_id' => $submittedByPlayerId,
        ]);
    }

    public function viaCsv(): static
    {
        return $this->state(fn (): array => [
            'submitted_via' => PlayerCustomFieldSubmission::SUBMITTED_VIA_CSV,
        ]);
    }
}

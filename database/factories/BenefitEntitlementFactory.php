<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Benefit\BenefitEntitlementSourceType;
use App\Enums\Benefit\BenefitEntitlementStatus;
use App\Models\BenefitDefinition;
use App\Models\BenefitEntitlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BenefitEntitlementFactory extends Factory
{
    protected $model = BenefitEntitlement::class;

    public function definition()
    {
        return [
            'benefit_definition_id' => BenefitDefinition::factory(),
            'player_user_id' => User::factory(),
            'remaining_uses' => 1,
            'valid_from' => null,
            'valid_until' => null,
            'source_type' => BenefitEntitlementSourceType::ManagerGrant,
            'source_id' => null,
            'status' => BenefitEntitlementStatus::Active,
            'inserted_at' => $this->faker->numberBetween(time() - (90 * 24 * 60 * 60), time()),
        ];
    }
}

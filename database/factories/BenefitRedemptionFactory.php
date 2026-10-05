<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Benefit\BenefitSelectionSource;
use App\Models\BenefitDefinition;
use App\Models\BenefitRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

class BenefitRedemptionFactory extends Factory
{
    protected $model = BenefitRedemption::class;

    public function definition()
    {
        return [
            // checkout_item_id must be supplied by the caller (no checkout-item factory yet)
            'benefit_definition_id' => BenefitDefinition::factory(),
            'entitlement_id' => null,
            'referral_attribution_id' => null,
            'amount_ron' => $this->faker->numberBetween(5, 20),
            'benefit_name' => $this->faker->words(2, true),
            'benefit_snapshot_hash' => null,
            'selection_source' => BenefitSelectionSource::Auto,
            'reason' => null,
            'inserted_at' => $this->faker->numberBetween(time() - (90 * 24 * 60 * 60), time()),
        ];
    }
}

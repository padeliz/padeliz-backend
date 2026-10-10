<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Benefit\BenefitConditionType;
use App\Models\BenefitCondition;
use App\Models\BenefitDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class BenefitConditionFactory extends Factory
{
    protected $model = BenefitCondition::class;

    public function definition()
    {
        return [
            'benefit_definition_id' => BenefitDefinition::factory(),
            'condition_type' => BenefitConditionType::StudentProofActive,
            'parameters' => [],
            'inserted_at' => $this->faker->numberBetween(time() - (90 * 24 * 60 * 60), time()),
        ];
    }

    public function studentProofActive(): static
    {
        return $this->state(fn () => [
            'condition_type' => BenefitConditionType::StudentProofActive,
            'parameters' => [],
        ]);
    }

    public function daysBeforeEditionDate(int $minDays): static
    {
        return $this->state(fn () => [
            'condition_type' => BenefitConditionType::DaysBeforeEditionDate,
            'parameters' => ['min_days' => $minDays],
        ]);
    }

    public function referrerSelected(): static
    {
        return $this->state(fn () => [
            'condition_type' => BenefitConditionType::ReferrerSelected,
            'parameters' => [],
        ]);
    }

    public function noPriorPaidRegistrationInEdition(): static
    {
        return $this->state(fn () => [
            'condition_type' => BenefitConditionType::NoPriorPaidRegistrationInEdition,
            'parameters' => [],
        ]);
    }
}

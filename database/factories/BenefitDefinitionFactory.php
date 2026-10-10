<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Benefit\BenefitAmountType;
use App\Enums\Benefit\BenefitConditionType;
use App\Enums\Benefit\BenefitExclusivity;
use App\Enums\Benefit\BenefitScopeType;
use App\Models\BenefitCondition;
use App\Models\BenefitDefinition;
use App\Models\PadelEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BenefitDefinitionFactory extends Factory
{
    protected $model = BenefitDefinition::class;

    public function definition()
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numerify('###'),
            'name' => ucwords($name),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'is_system' => false,
            'amount_type' => BenefitAmountType::FixedRon,
            'amount_value' => $this->faker->numberBetween(5, 20),
            'max_discount_ron' => null,
            'scope_type' => BenefitScopeType::Lifetime,
            'club_id' => null,
            'valid_from' => null,
            'valid_until' => null,
            'player_optional' => false,
            'stack_group' => null,
            'exclusivity' => BenefitExclusivity::Stackable,
            'priority' => 100,
            'max_redemptions_per_player' => null,
            'max_redemptions_global' => null,
            'max_redemptions_per_event' => null,
            'inserted_at' => $this->faker->numberBetween(time() - (90 * 24 * 60 * 60), time()),
        ];
    }

    public function fixedRon(int $amount): static
    {
        return $this->state(fn () => [
            'amount_type' => BenefitAmountType::FixedRon,
            'amount_value' => $amount,
        ]);
    }

    public function percent(int $percent, ?int $maxDiscountRon = null): static
    {
        return $this->state(fn () => [
            'amount_type' => BenefitAmountType::Percent,
            'amount_value' => $percent,
            'max_discount_ron' => $maxDiscountRon,
        ]);
    }

    public function stackable(?string $stackGroup = null): static
    {
        return $this->state(fn () => [
            'exclusivity' => BenefitExclusivity::Stackable,
            'stack_group' => $stackGroup,
        ]);
    }

    public function exclusiveInGroup(string $group): static
    {
        return $this->state(fn () => [
            'exclusivity' => BenefitExclusivity::ExclusiveInGroup,
            'stack_group' => $group,
        ]);
    }

    public function exclusiveGlobal(): static
    {
        return $this->state(fn () => [
            'exclusivity' => BenefitExclusivity::ExclusiveGlobal,
            'stack_group' => null,
        ]);
    }

    public function playerOptional(bool $optional = true): static
    {
        return $this->state(fn () => [
            'player_optional' => $optional,
        ]);
    }

    public function auto(): static
    {
        return $this->playerOptional(false);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn () => [
            'priority' => $priority,
        ]);
    }

    public function lifetime(): static
    {
        return $this->state(fn () => [
            'scope_type' => BenefitScopeType::Lifetime,
            'club_id' => null,
        ]);
    }

    public function dateRange(?int $validFrom = null, ?int $validUntil = null): static
    {
        return $this->state(fn () => [
            'scope_type' => BenefitScopeType::DateRange,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
        ]);
    }

    public function forClub(int $clubId): static
    {
        return $this->state(fn () => [
            'scope_type' => BenefitScopeType::Club,
            'club_id' => $clubId,
        ]);
    }

    public function forEvents(PadelEvent ...$editions): static
    {
        return $this->state(fn () => [
            'scope_type' => BenefitScopeType::PadelEvent,
            'club_id' => null,
        ])->afterCreating(function (BenefitDefinition $benefit) use ($editions): void {
            $now = time();
            $sync = [];
            foreach ($editions as $event) {
                $sync[$event->id_event] = [
                    'inserted_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $benefit->events()->sync($sync);
        });
    }

    public function withMaxRedemptions(
        ?int $perPlayer = null,
        ?int $global = null,
        ?int $perEdition = null,
    ): static {
        return $this->state(fn () => [
            'max_redemptions_per_player' => $perPlayer,
            'max_redemptions_global' => $global,
            'max_redemptions_per_event' => $perEdition,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function withStudentProofActive(): static
    {
        return $this->afterCreating(function (BenefitDefinition $benefit): void {
            BenefitCondition::factory()->studentProofActive()->create([
                'benefit_definition_id' => $benefit->id_benefit,
            ]);
        });
    }

    public function withDaysBeforeEditionDate(int $minDays): static
    {
        return $this->afterCreating(function (BenefitDefinition $benefit) use ($minDays): void {
            BenefitCondition::factory()->daysBeforeEditionDate($minDays)->create([
                'benefit_definition_id' => $benefit->id_benefit,
            ]);
        });
    }

    public function withCondition(BenefitConditionType $type, array $parameters = []): static
    {
        return $this->afterCreating(function (BenefitDefinition $benefit) use ($type, $parameters): void {
            BenefitCondition::factory()->create([
                'benefit_definition_id' => $benefit->id_benefit,
                'condition_type' => $type,
                'parameters' => $parameters,
            ]);
        });
    }
}

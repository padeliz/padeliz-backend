<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\Benefit\BenefitAmountType;
use App\Enums\Benefit\BenefitConditionType;
use App\Enums\Benefit\BenefitExclusivity;
use App\Enums\Benefit\BenefitScopeType;
use App\Models\BenefitDefinition;
use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BenefitDefinitionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
    }

    public function test_manager_can_crud_benefit_definition_with_conditions_and_events(): void
    {
        Sanctum::actingAs($this->manager);
        $event = PadelEvent::factory()->create();

        $created = $this->postJson('/api/v1/manager/benefit-definitions', [
            'slug' => 'vip-weekend',
            'name' => 'VIP Weekend',
            'description' => 'VIP discount',
            'is_active' => true,
            'amount_type' => BenefitAmountType::FixedRon->value,
            'amount_value' => 10,
            'scope_type' => BenefitScopeType::PadelEvent->value,
            'exclusivity' => BenefitExclusivity::Stackable->value,
            'priority' => 50,
            'event_ids' => [$event->id_event],
            'conditions' => [
                [
                    'condition_type' => BenefitConditionType::DaysBeforeEditionDate->value,
                    'parameters' => ['min_days' => 3],
                ],
            ],
        ]);

        $created->assertStatus(201)
            ->assertJsonPath('data.slug', 'vip-weekend')
            ->assertJsonPath('data.event_ids.0', $event->id_event)
            ->assertJsonPath('data.conditions.0.condition_type', BenefitConditionType::DaysBeforeEditionDate->value);

        $id = (int) $created->json('data.id_benefit');

        $this->getJson('/api/v1/manager/benefit-definitions')
            ->assertStatus(200)
            ->assertJsonFragment(['slug' => 'vip-weekend']);

        $this->getJson("/api/v1/manager/benefit-definitions/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id_benefit', $id);

        $this->putJson("/api/v1/manager/benefit-definitions/{$id}", [
            'slug' => 'vip-weekend',
            'name' => 'VIP Weekend Updated',
            'amount_type' => BenefitAmountType::Percent->value,
            'amount_value' => 15,
            'scope_type' => BenefitScopeType::Lifetime->value,
            'exclusivity' => BenefitExclusivity::Stackable->value,
            'conditions' => [],
            'event_ids' => [],
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'VIP Weekend Updated')
            ->assertJsonPath('data.amount_type', BenefitAmountType::Percent->value)
            ->assertJsonPath('data.event_ids', []);

        $this->postJson("/api/v1/manager/benefit-definitions/{$id}/deactivate")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->postJson("/api/v1/manager/benefit-definitions/{$id}/activate")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->deleteJson("/api/v1/manager/benefit-definitions/{$id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('benefit_definitions', ['id_benefit' => $id]);
    }

    public function test_manager_cannot_delete_system_benefit(): void
    {
        Sanctum::actingAs($this->manager);

        $system = BenefitDefinition::factory()->create([
            'slug' => 'manual_adjustment_test',
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/manager/benefit-definitions/{$system->id_benefit}")
            ->assertStatus(422);

        $this->assertDatabaseHas('benefit_definitions', ['id_benefit' => $system->id_benefit]);
    }
}

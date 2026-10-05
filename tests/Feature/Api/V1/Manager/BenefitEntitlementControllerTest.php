<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\Benefit\BenefitEntitlementStatus;
use App\Models\BenefitDefinition;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BenefitEntitlementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Player $player;

    private BenefitDefinition $benefit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->player = Player::factory()->create();
        $this->benefit = BenefitDefinition::factory()->create([
            'is_active' => true,
            'player_optional' => true,
        ]);
    }

    public function test_manager_can_grant_list_show_revoke_and_extend_entitlement(): void
    {
        Sanctum::actingAs($this->manager);

        $granted = $this->postJson('/api/v1/manager/benefit-entitlements', [
            'benefit_definition_id' => $this->benefit->id_benefit,
            'player_id' => $this->player->id_player,
            'remaining_uses' => 2,
            'valid_until' => time() + 3600,
        ]);

        $granted->assertStatus(201)
            ->assertJsonPath('data.player_id', $this->player->id_player)
            ->assertJsonPath('data.status', BenefitEntitlementStatus::Active->value)
            ->assertJsonPath('data.source_type', 'manager_grant');

        $id = (int) $granted->json('data.id_entitlement');

        $this->getJson('/api/v1/manager/benefit-entitlements')
            ->assertStatus(200)
            ->assertJsonFragment(['id_entitlement' => $id]);

        $this->getJson("/api/v1/manager/benefit-entitlements/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id_entitlement', $id);

        $extendedUntil = time() + 86400;
        $this->patchJson("/api/v1/manager/benefit-entitlements/{$id}/extend", [
            'valid_until' => $extendedUntil,
        ])->assertStatus(200)
            ->assertJsonPath('data.valid_until', $extendedUntil);

        $this->postJson("/api/v1/manager/benefit-entitlements/{$id}/revoke")
            ->assertStatus(200)
            ->assertJsonPath('data.status', BenefitEntitlementStatus::Revoked->value);

        $this->assertDatabaseHas('benefit_entitlements', [
            'id_entitlement' => $id,
            'status' => BenefitEntitlementStatus::Revoked->value,
        ]);
    }

    public function test_cannot_grant_inactive_benefit(): void
    {
        Sanctum::actingAs($this->manager);
        $inactive = BenefitDefinition::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/manager/benefit-entitlements', [
            'benefit_definition_id' => $inactive->id_benefit,
            'player_id' => $this->player->id_player,
        ])->assertStatus(422);
    }
}

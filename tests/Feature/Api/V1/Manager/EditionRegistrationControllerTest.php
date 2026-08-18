<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionRegistration;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Edition $edition;

    private EditionDivision $editionDivision;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->edition = Edition::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);
        $division = Division::factory()->create();
        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 1,
        ]);
        $this->player = Player::factory()->create();
    }

    public function test_manager_can_index_show_store_update_patch_destroy(): void
    {
        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);
        $created->assertStatus(201)
            ->assertJsonPath('data.is_minor', false)
            ->assertJsonPath('data.is_student', false)
            ->assertJsonPath('data.discount', 0)
            ->assertJsonPath('data.payment', 0);
        $id = $created->json('data.id_registration');

        $this->getJson('/api/v1/manager/edition-registrations')->assertStatus(200)
            ->assertJsonMissingPath('data.0.player_history');

        $this->getJson("/api/v1/manager/edition-registrations/{$id}")->assertStatus(200)
            ->assertJsonPath('data.id_registration', $id);

        $this->putJson("/api/v1/manager/edition-registrations/{$id}", $this->putPayload([
            'payment' => 40,
        ]))->assertStatus(200)->assertJsonPath('data.payment', 40);

        $this->patchJson("/api/v1/manager/edition-registrations/{$id}", [
            'payment' => 41,
        ])->assertStatus(200)->assertJsonPath('data.payment', 41);

        $this->deleteJson("/api/v1/manager/edition-registrations/{$id}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('edition_registrations', ['id_registration' => $id]);
    }

    public function test_manager_can_bulk_delete(): void
    {
        Sanctum::actingAs($this->manager);
        $rows = EditionRegistration::factory()->count(2)->create([
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 0,
        ]);

        $this->deleteJson('/api/v1/manager/edition-registrations/bulk', [
            'registration_ids' => $rows->pluck('id_registration')->all(),
        ])->assertStatus(200)->assertJsonPath('data.total_successful', 2);
    }

    public function test_pagination_and_auth(): void
    {
        EditionRegistration::factory()->count(3)->create([
            'edition_id' => $this->edition->id_edition,
            'is_student' => false,
            'payment' => 0,
        ]);

        $this->getJson('/api/v1/manager/edition-registrations')->assertStatus(401);

        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        $this->getJson('/api/v1/manager/edition-registrations')->assertStatus(403);

        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/manager/edition-registrations/99999')->assertStatus(404);
        $this->getJson('/api/v1/manager/edition-registrations?per_page=2')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_store_validation(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/edition-registrations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'player_id']);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => 99999,
            'player_id' => 99999,
            'edition_division_id' => 99999,
        ])->assertStatus(422);
    }

    public function test_store_null_edition_division_and_filters(): void
    {
        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => null,
        ]);
        $created->assertStatus(201)->assertJsonPath('data.edition_division_id', null);
        $id = $created->json('data.id_registration');

        $byEdition = $this->getJson(
            '/api/v1/manager/edition-registrations?filters[edition_id]=' . $this->edition->id_edition
        );
        $byEdition->assertStatus(200);
        $this->assertNotNull(collect($byEdition->json('data'))->firstWhere('id_registration', $id));

        $byPlayer = $this->getJson(
            '/api/v1/manager/edition-registrations?filters[player_id]=' . $this->player->id_player
        );
        $this->assertNotNull(collect($byPlayer->json('data'))->firstWhere('id_registration', $id));

        $byEd = $this->getJson(
            '/api/v1/manager/edition-registrations?filters[edition_division_id]=' . $this->editionDivision->id_edition_division
        );
        $this->assertNull(collect($byEd->json('data'))->firstWhere('id_registration', $id));
    }

    public function test_store_rejects_edition_division_from_another_edition(): void
    {
        Sanctum::actingAs($this->manager);
        $other = EditionDivision::factory()->create();

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $other->id_edition_division,
        ])->assertStatus(422);
    }

    public function test_xor_store_cases(): void
    {
        Sanctum::actingAs($this->manager);
        $second = $this->secondEditionDivision();

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(422);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $second->id_edition_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => null,
        ])->assertStatus(422);
    }

    public function test_xor_null_store_cases(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
        ])->assertStatus(422);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(422);

        $otherEdition = Edition::factory()->create(['date' => now()->addDays(40)->format('Y-m-d')]);
        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $otherEdition->id_edition,
            'player_id' => $this->player->id_player,
        ])->assertStatus(201);
    }

    public function test_xor_update_and_patch_merged_target(): void
    {
        Sanctum::actingAs($this->manager);
        $second = $this->secondEditionDivision();

        $sole = $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
        ]);
        $sole->assertStatus(201);
        $soleId = $sole->json('data.id_registration');

        $this->patchJson("/api/v1/manager/edition-registrations/{$soleId}", [
            'payment' => 12,
        ])->assertStatus(200)->assertJsonPath('data.edition_division_id', null);

        $this->putJson("/api/v1/manager/edition-registrations/{$soleId}", $this->putPayload([
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]))->assertStatus(200)->assertJsonPath('data.edition_division_id', $this->editionDivision->id_edition_division);

        $this->patchJson("/api/v1/manager/edition-registrations/{$soleId}", [
            'edition_division_id' => null,
        ])->assertStatus(200)->assertJsonPath('data.edition_division_id', null);

        $this->patchJson("/api/v1/manager/edition-registrations/{$soleId}", [
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(200);

        $secondRow = $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $second->id_edition_division,
        ]);
        $secondRow->assertStatus(201);

        $this->patchJson("/api/v1/manager/edition-registrations/{$secondRow->json('data.id_registration')}", [
            'edition_division_id' => null,
        ])->assertStatus(422);

        $this->putJson("/api/v1/manager/edition-registrations/{$secondRow->json('data.id_registration')}", $this->putPayload([
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]))->assertStatus(422);
    }

    public function test_finalize_blocks_mutations_but_allows_index_show(): void
    {
        Sanctum::actingAs($this->manager);
        $registration = EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 0,
        ]);
        $this->edition->update(['finalized_at' => time()]);

        $this->getJson('/api/v1/manager/edition-registrations')->assertStatus(200);
        $this->getJson("/api/v1/manager/edition-registrations/{$registration->id_registration}")->assertStatus(200);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => Player::factory()->create()->id_player,
        ])->assertStatus(409);

        $this->putJson("/api/v1/manager/edition-registrations/{$registration->id_registration}", $this->putPayload())
            ->assertStatus(409);
        $this->patchJson("/api/v1/manager/edition-registrations/{$registration->id_registration}", ['payment' => 1])
            ->assertStatus(409);
        $this->deleteJson("/api/v1/manager/edition-registrations/{$registration->id_registration}")
            ->assertStatus(409);
        $this->deleteJson('/api/v1/manager/edition-registrations/bulk', [
            'registration_ids' => [$registration->id_registration],
        ])->assertStatus(409);
    }

    public function test_manager_can_store_for_past_date_and_over_players_limit(): void
    {
        Sanctum::actingAs($this->manager);
        $this->edition->update(['date' => now()->subDay()->format('Y-m-d')]);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => Player::factory()->create()->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);
    }

    public function test_reorder_is_not_registered_and_index_omits_player_history(): void
    {
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/manager/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
        ])->assertStatus(201);

        $this->patchJson('/api/v1/manager/edition-registrations/reorder', [])
            ->assertStatus(404);

        $index = $this->getJson(
            '/api/v1/manager/edition-registrations?filters[edition_id]=' . $this->edition->id_edition
        );
        $index->assertStatus(200);
        $this->assertArrayNotHasKey('player_history', $index->json('data.0'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function putPayload(array $overrides = []): array
    {
        return array_merge([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => false,
            'is_student' => false,
            'discount' => 0,
            'payment' => 0,
        ], $overrides);
    }

    private function secondEditionDivision(): EditionDivision
    {
        return EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => Division::factory(),
            'players_limit' => 8,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use App\Models\PlayerPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerPhoneControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create();
        $this->player = Player::factory()->create();
    }

    public function test_index_requires_manager(): void
    {
        $this->getJson('/api/v1/manager/player-phones')->assertStatus(401);

        Sanctum::actingAs(Player::factory()->playerOnly()->create());
        $this->getJson('/api/v1/manager/player-phones')->assertStatus(403);
    }

    public function test_manager_can_list_phones_filtered_by_player(): void
    {
        Sanctum::actingAs($this->manager);

        $mine = PlayerPhone::factory()->create(['player_id' => $this->player->id_player]);
        PlayerPhone::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/manager/player-phones?filters[player_id]=' . $this->player->id_player);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id_player_phone', 'player_id', 'phone', 'purposes', 'is_primary', 'verified_at', 'inserted_at', 'updated_at']],
                'meta',
            ]);
        $rows = collect($response->json('data'));
        $this->assertContains($mine->id_player_phone, $rows->pluck('id_player_phone')->all());
        $this->assertSame([$this->player->id_player], $rows->pluck('player_id')->unique()->values()->all());
    }

    public function test_manager_can_search_phones(): void
    {
        Sanctum::actingAs($this->manager);

        PlayerPhone::factory()->create(['phone' => '+99900011122']);
        PlayerPhone::factory()->create(['phone' => '+40799999999']);

        $response = $this->getJson('/api/v1/manager/player-phones?q=9990001112');

        $response->assertStatus(200);
        $this->assertSame(['+99900011122'], collect($response->json('data'))->pluck('phone')->all());
    }

    public function test_manager_can_show_phone(): void
    {
        Sanctum::actingAs($this->manager);

        $phone = PlayerPhone::factory()->create(['player_id' => $this->player->id_player]);

        $this->getJson('/api/v1/manager/player-phones/' . $phone->id_player_phone)
            ->assertStatus(200)
            ->assertJsonPath('data.id_player_phone', $phone->id_player_phone)
            ->assertJsonPath('data.player_id', $this->player->id_player);

        $this->getJson('/api/v1/manager/player-phones/999999')->assertStatus(404);
    }

    public function test_manager_can_create_phone_and_number_is_normalized(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/player-phones', [
            'player_id' => $this->player->id_player,
            'phone' => '+40 712 345 678',
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.phone', '+40712345678')
            ->assertJsonPath('data.is_primary', true);
        $this->assertDatabaseHas('player_phones', [
            'player_id' => $this->player->id_player,
            'phone' => '+40712345678',
        ]);
    }

    public function test_store_validates_input(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/player-phones', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_id', 'phone', 'purposes']);

        $this->postJson('/api/v1/manager/player-phones', [
            'player_id' => 999999,
            'phone' => 'not-a-number',
            'purposes' => ['sms'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['player_id', 'phone', 'purposes.0']);
    }

    public function test_store_rejects_duplicate_phone_after_normalization(): void
    {
        Sanctum::actingAs($this->manager);

        PlayerPhone::factory()->create(['phone' => '+40712345678']);

        $this->postJson('/api/v1/manager/player-phones', [
            'player_id' => $this->player->id_player,
            'phone' => '+40 712 345 678',
            'purposes' => ['contact'],
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_marking_a_phone_primary_demotes_the_players_other_primary(): void
    {
        Sanctum::actingAs($this->manager);

        $first = PlayerPhone::factory()->create([
            'player_id' => $this->player->id_player,
            'is_primary' => true,
        ]);
        $otherPlayerPrimary = PlayerPhone::factory()->create(['is_primary' => true]);

        $this->postJson('/api/v1/manager/player-phones', [
            'player_id' => $this->player->id_player,
            'phone' => '+40700000001',
            'purposes' => ['contact'],
            'is_primary' => true,
        ])->assertStatus(201);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($otherPlayerPrimary->refresh()->is_primary);
    }

    public function test_manager_can_update_phone(): void
    {
        Sanctum::actingAs($this->manager);

        $phone = PlayerPhone::factory()->create([
            'player_id' => $this->player->id_player,
            'phone' => '+40711111111',
            'purposes' => ['contact'],
            'is_primary' => false,
        ]);

        $this->putJson('/api/v1/manager/player-phones/' . $phone->id_player_phone, [
            'phone' => '+40722222222',
            'purposes' => ['contact', 'login'],
            'is_primary' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.phone', '+40722222222')
            ->assertJsonPath('data.purposes', ['contact', 'login'])
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('player_phones', [
            'id_player_phone' => $phone->id_player_phone,
            'phone' => '+40722222222',
        ]);
    }

    public function test_update_may_keep_own_phone_but_not_take_another(): void
    {
        Sanctum::actingAs($this->manager);

        $phone = PlayerPhone::factory()->create(['phone' => '+40711111111']);
        PlayerPhone::factory()->create(['phone' => '+40733333333']);

        $this->putJson('/api/v1/manager/player-phones/' . $phone->id_player_phone, [
            'phone' => '+40711111111',
        ])->assertStatus(200);

        $this->putJson('/api/v1/manager/player-phones/' . $phone->id_player_phone, [
            'phone' => '+40733333333',
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_manager_can_delete_phone(): void
    {
        Sanctum::actingAs($this->manager);

        $phone = PlayerPhone::factory()->create();

        $this->deleteJson('/api/v1/manager/player-phones/' . $phone->id_player_phone)
            ->assertStatus(200);

        $this->assertDatabaseMissing('player_phones', ['id_player_phone' => $phone->id_player_phone]);
        $this->deleteJson('/api/v1/manager/player-phones/' . $phone->id_player_phone)->assertStatus(404);
    }

    public function test_manager_can_bulk_delete_phones(): void
    {
        Sanctum::actingAs($this->manager);

        $phones = PlayerPhone::factory()->count(2)->create();
        $keep = PlayerPhone::factory()->create();
        $before = PlayerPhone::query()->count();

        $response = $this->deleteJson('/api/v1/manager/player-phones/bulk', [
            'player_phone_ids' => $phones->pluck('id_player_phone')->all(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_successful', 2)
            ->assertJsonPath('data.total_failed', 0);
        $this->assertDatabaseHas('player_phones', ['id_player_phone' => $keep->id_player_phone]);
        $this->assertSame($before - 2, PlayerPhone::query()->count());
    }

    public function test_bulk_delete_requires_ids(): void
    {
        Sanctum::actingAs($this->manager);

        $this->deleteJson('/api/v1/manager/player-phones/bulk', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_phone_ids']);
    }

    public function test_phone_model_exposes_resource_metadata(): void
    {
        $this->assertContains('player_id', PlayerPhone::getFilterableFields());
        $this->assertSame('phone', PlayerPhone::$displayField);
    }
}

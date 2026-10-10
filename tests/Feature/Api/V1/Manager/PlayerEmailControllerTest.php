<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use App\Models\PlayerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerEmailControllerTest extends TestCase
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
        $this->getJson('/api/v1/manager/player-emails')->assertStatus(401);

        Sanctum::actingAs(Player::factory()->playerOnly()->create());
        $this->getJson('/api/v1/manager/player-emails')->assertStatus(403);
    }

    public function test_manager_can_list_emails_filtered_by_player(): void
    {
        Sanctum::actingAs($this->manager);

        $mine = PlayerEmail::factory()->create(['player_id' => $this->player->id_player]);
        PlayerEmail::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/manager/player-emails?filters[player_id]=' . $this->player->id_player);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id_player_email', 'player_id', 'email', 'purposes', 'is_primary', 'verified_at', 'inserted_at', 'updated_at']],
                'meta',
            ]);
        $rows = collect($response->json('data'));
        $this->assertContains($mine->id_player_email, $rows->pluck('id_player_email')->all());
        $this->assertSame([$this->player->id_player], $rows->pluck('player_id')->unique()->values()->all());
    }

    public function test_manager_can_search_emails(): void
    {
        Sanctum::actingAs($this->manager);

        PlayerEmail::factory()->create(['email' => 'zzsearchtoken@example.test']);
        PlayerEmail::factory()->create(['email' => 'bob@example.test']);

        $response = $this->getJson('/api/v1/manager/player-emails?q=zzsearchtoken');

        $response->assertStatus(200);
        $this->assertSame(['zzsearchtoken@example.test'], collect($response->json('data'))->pluck('email')->all());
    }

    public function test_manager_can_show_email(): void
    {
        Sanctum::actingAs($this->manager);

        $email = PlayerEmail::factory()->create(['player_id' => $this->player->id_player]);

        $this->getJson('/api/v1/manager/player-emails/' . $email->id_player_email)
            ->assertStatus(200)
            ->assertJsonPath('data.id_player_email', $email->id_player_email)
            ->assertJsonPath('data.player_id', $this->player->id_player);

        $this->getJson('/api/v1/manager/player-emails/999999')->assertStatus(404);
    }

    public function test_manager_can_create_email(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/player-emails', [
            'player_id' => $this->player->id_player,
            'email' => 'ana@example.test',
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'ana@example.test')
            ->assertJsonPath('data.is_primary', true);
        $this->assertDatabaseHas('player_emails', [
            'player_id' => $this->player->id_player,
            'email' => 'ana@example.test',
        ]);
    }

    public function test_store_validates_input(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/player-emails', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_id', 'email', 'purposes']);

        $this->postJson('/api/v1/manager/player-emails', [
            'player_id' => 999999,
            'email' => 'not-an-email',
            'purposes' => ['sms'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['player_id', 'email', 'purposes.0']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        Sanctum::actingAs($this->manager);

        PlayerEmail::factory()->create(['email' => 'ana@example.test']);

        $this->postJson('/api/v1/manager/player-emails', [
            'player_id' => $this->player->id_player,
            'email' => 'ana@example.test',
            'purposes' => ['contact'],
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_marking_an_email_primary_demotes_the_players_other_primary(): void
    {
        Sanctum::actingAs($this->manager);

        $first = PlayerEmail::factory()->create([
            'player_id' => $this->player->id_player,
            'is_primary' => true,
        ]);
        $otherPlayerPrimary = PlayerEmail::factory()->create(['is_primary' => true]);

        $this->postJson('/api/v1/manager/player-emails', [
            'player_id' => $this->player->id_player,
            'email' => 'new-primary@example.test',
            'purposes' => ['contact'],
            'is_primary' => true,
        ])->assertStatus(201);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($otherPlayerPrimary->refresh()->is_primary);
    }

    public function test_manager_can_update_email(): void
    {
        Sanctum::actingAs($this->manager);

        $email = PlayerEmail::factory()->create([
            'player_id' => $this->player->id_player,
            'email' => 'one@example.test',
            'purposes' => ['contact'],
            'is_primary' => false,
        ]);

        $this->putJson('/api/v1/manager/player-emails/' . $email->id_player_email, [
            'email' => 'two@example.test',
            'purposes' => ['contact', 'login'],
            'is_primary' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.email', 'two@example.test')
            ->assertJsonPath('data.purposes', ['contact', 'login'])
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('player_emails', [
            'id_player_email' => $email->id_player_email,
            'email' => 'two@example.test',
        ]);
    }

    public function test_update_may_keep_own_email_but_not_take_another(): void
    {
        Sanctum::actingAs($this->manager);

        $email = PlayerEmail::factory()->create(['email' => 'one@example.test']);
        PlayerEmail::factory()->create(['email' => 'three@example.test']);

        $this->putJson('/api/v1/manager/player-emails/' . $email->id_player_email, [
            'email' => 'one@example.test',
        ])->assertStatus(200);

        $this->putJson('/api/v1/manager/player-emails/' . $email->id_player_email, [
            'email' => 'three@example.test',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_manager_can_delete_email(): void
    {
        Sanctum::actingAs($this->manager);

        $email = PlayerEmail::factory()->create();

        $this->deleteJson('/api/v1/manager/player-emails/' . $email->id_player_email)
            ->assertStatus(200);

        $this->assertDatabaseMissing('player_emails', ['id_player_email' => $email->id_player_email]);
        $this->deleteJson('/api/v1/manager/player-emails/' . $email->id_player_email)->assertStatus(404);
    }

    public function test_manager_can_bulk_delete_emails(): void
    {
        Sanctum::actingAs($this->manager);

        $emails = PlayerEmail::factory()->count(2)->create();
        $keep = PlayerEmail::factory()->create();
        $before = PlayerEmail::query()->count();

        $response = $this->deleteJson('/api/v1/manager/player-emails/bulk', [
            'player_email_ids' => $emails->pluck('id_player_email')->all(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_successful', 2)
            ->assertJsonPath('data.total_failed', 0);
        $this->assertDatabaseHas('player_emails', ['id_player_email' => $keep->id_player_email]);
        $this->assertSame($before - 2, PlayerEmail::query()->count());
    }

    public function test_bulk_delete_requires_ids(): void
    {
        Sanctum::actingAs($this->manager);

        $this->deleteJson('/api/v1/manager/player-emails/bulk', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_email_ids']);
    }

    public function test_email_model_exposes_resource_metadata(): void
    {
        $this->assertContains('player_id', PlayerEmail::getFilterableFields());
        $this->assertSame('email', PlayerEmail::$displayField);
    }
}

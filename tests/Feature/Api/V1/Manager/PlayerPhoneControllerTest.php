<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerPhoneControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->player = User::factory()->create();
    }

    public function test_index_requires_manager(): void
    {
        $this->getJson('/api/v1/manager/player-phones')->assertStatus(401);

        Sanctum::actingAs(User::factory()->playerOnly()->create());
        $this->getJson('/api/v1/manager/player-phones')->assertStatus(403);
    }

    public function test_manager_can_list_phones_filtered_by_player(): void
    {
        Sanctum::actingAs($this->manager);

        $mine = UserPhone::factory()->create(['user_id' => $this->player->id_user]);
        UserPhone::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/manager/player-phones?filters[user_id]=' . $this->player->id_user);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id_user_phone', 'user_id', 'phone', 'purposes', 'is_primary', 'verified_at', 'inserted_at', 'updated_at']],
                'meta',
            ]);
        $rows = collect($response->json('data'));
        $this->assertContains($mine->id_user_phone, $rows->pluck('id_user_phone')->all());
        $this->assertSame([$this->player->id_user], $rows->pluck('user_id')->unique()->values()->all());
    }

    public function test_manager_can_search_phones(): void
    {
        Sanctum::actingAs($this->manager);

        UserPhone::factory()->create(['phone' => '+99900011122']);
        UserPhone::factory()->create(['phone' => '+40799999999']);

        $response = $this->getJson('/api/v1/manager/player-phones?q=9990001112');

        $response->assertStatus(200);
        $this->assertSame(['+99900011122'], collect($response->json('data'))->pluck('phone')->all());
    }

    public function test_manager_can_show_phone(): void
    {
        Sanctum::actingAs($this->manager);

        $phone = UserPhone::factory()->create(['user_id' => $this->player->id_user]);

        $this->getJson('/api/v1/manager/player-phones/' . $phone->id_user_phone)
            ->assertStatus(200)
            ->assertJsonPath('data.id_user_phone', $phone->id_user_phone)
            ->assertJsonPath('data.user_id', $this->player->id_user);

        $this->getJson('/api/v1/manager/player-phones/999999')->assertStatus(404);
    }

    public function test_manager_can_create_phone_and_number_is_normalized(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/player-phones', [
            'user_id' => $this->player->id_user,
            'phone' => '+40 712 345 678',
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.phone', '+40712345678')
            ->assertJsonPath('data.is_primary', true);
        $this->assertDatabaseHas('user_phones', [
            'user_id' => $this->player->id_user,
            'phone' => '+40712345678',
        ]);
    }

    public function test_store_validates_input(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/player-phones', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'phone', 'purposes']);

        $this->postJson('/api/v1/manager/player-phones', [
            'user_id' => 999999,
            'phone' => 'not-a-number',
            'purposes' => ['sms'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'phone', 'purposes.0']);
    }

    public function test_store_rejects_duplicate_phone_after_normalization(): void
    {
        Sanctum::actingAs($this->manager);

        UserPhone::factory()->create(['phone' => '+40712345678']);

        $this->postJson('/api/v1/manager/player-phones', [
            'user_id' => $this->player->id_user,
            'phone' => '+40 712 345 678',
            'purposes' => ['contact'],
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_marking_a_phone_primary_demotes_the_players_other_primary(): void
    {
        Sanctum::actingAs($this->manager);

        $first = UserPhone::factory()->create([
            'user_id' => $this->player->id_user,
            'is_primary' => true,
        ]);
        $otherPlayerPrimary = UserPhone::factory()->create(['is_primary' => true]);

        $this->postJson('/api/v1/manager/player-phones', [
            'user_id' => $this->player->id_user,
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

        $phone = UserPhone::factory()->create([
            'user_id' => $this->player->id_user,
            'phone' => '+40711111111',
            'purposes' => ['contact'],
            'is_primary' => false,
        ]);

        $this->putJson('/api/v1/manager/player-phones/' . $phone->id_user_phone, [
            'phone' => '+40722222222',
            'purposes' => ['contact', 'login'],
            'is_primary' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.phone', '+40722222222')
            ->assertJsonPath('data.purposes', ['contact', 'login'])
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('user_phones', [
            'id_user_phone' => $phone->id_user_phone,
            'phone' => '+40722222222',
        ]);
    }

    public function test_update_may_keep_own_phone_but_not_take_another(): void
    {
        Sanctum::actingAs($this->manager);

        $phone = UserPhone::factory()->create(['phone' => '+40711111111']);
        UserPhone::factory()->create(['phone' => '+40733333333']);

        $this->putJson('/api/v1/manager/player-phones/' . $phone->id_user_phone, [
            'phone' => '+40711111111',
        ])->assertStatus(200);

        $this->putJson('/api/v1/manager/player-phones/' . $phone->id_user_phone, [
            'phone' => '+40733333333',
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_manager_can_delete_phone(): void
    {
        Sanctum::actingAs($this->manager);

        $phone = UserPhone::factory()->create();

        $this->deleteJson('/api/v1/manager/player-phones/' . $phone->id_user_phone)
            ->assertStatus(200);

        $this->assertDatabaseMissing('user_phones', ['id_user_phone' => $phone->id_user_phone]);
        $this->deleteJson('/api/v1/manager/player-phones/' . $phone->id_user_phone)->assertStatus(404);
    }

    public function test_manager_can_bulk_delete_phones(): void
    {
        Sanctum::actingAs($this->manager);

        $phones = UserPhone::factory()->count(2)->create();
        $keep = UserPhone::factory()->create();
        $before = UserPhone::query()->count();

        $response = $this->deleteJson('/api/v1/manager/player-phones/bulk', [
            'player_phone_ids' => $phones->pluck('id_user_phone')->all(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_successful', 2)
            ->assertJsonPath('data.total_failed', 0);
        $this->assertDatabaseHas('user_phones', ['id_user_phone' => $keep->id_user_phone]);
        $this->assertSame($before - 2, UserPhone::query()->count());
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
        $this->assertContains('user_id', UserPhone::getFilterableFields());
        $this->assertSame('phone', UserPhone::$displayField);
    }
}

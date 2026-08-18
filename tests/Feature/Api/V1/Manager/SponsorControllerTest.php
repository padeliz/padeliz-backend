<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\EditionLottery;
use App\Models\EditionLotteryPrize;
use App\Models\Player;
use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SponsorControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
    }

    public function test_create_enforces_unique_name(): void
    {
        Sanctum::actingAs($this->manager);

        Sponsor::factory()->create(['name' => 'Unique Sponsor']);

        $response = $this->postJson('/api/v1/manager/sponsors', [
            'name' => 'Unique Sponsor',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_enforces_unique_club_id(): void
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();
        Sponsor::factory()->forClub($club)->create();

        $response = $this->postJson('/api/v1/manager/sponsors', [
            'name' => 'Another Name',
            'club_id' => $club->id_club,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id']);
    }

    public function test_player_ids_full_replace_on_update(): void
    {
        Sanctum::actingAs($this->manager);

        $playerA = Player::factory()->create();
        $playerB = Player::factory()->create();

        $create = $this->postJson('/api/v1/manager/sponsors', [
            'name' => 'Players Sponsor',
            'player_ids' => [(int) $playerA->id_player, (int) $playerB->id_player],
        ]);
        $create->assertStatus(201);
        $sponsorId = $create->json('data.id_sponsor');
        $this->assertCount(2, $create->json('data.players'));

        $before = $this->getJson("/api/v1/manager/sponsors/{$sponsorId}");
        $before->assertOk();
        $this->assertCount(2, $before->json('data.players'));

        $update = $this->putJson("/api/v1/manager/sponsors/{$sponsorId}", [
            'name' => 'Players Sponsor',
            'player_ids' => [],
        ]);
        $update->assertOk();
        $this->assertSame([], $update->json('data.players'));
    }

    public function test_delete_blocked_by_lottery_prizes(): void
    {
        Sanctum::actingAs($this->manager);

        $sponsor = Sponsor::factory()->create();
        $lottery = EditionLottery::factory()->create();
        EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'sponsor_id' => $sponsor->id_sponsor,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);

        $response = $this->deleteJson("/api/v1/manager/sponsors/{$sponsor->id_sponsor}");
        $response->assertStatus(409);
        $this->assertDatabaseHas('sponsors', ['id_sponsor' => $sponsor->id_sponsor]);
    }

    public function test_delete_blocked_by_sponsor_prizes(): void
    {
        Sanctum::actingAs($this->manager);

        $sponsor = Sponsor::factory()->create();
        SponsorPrize::factory()->create([
            'sponsor_id' => $sponsor->id_sponsor,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);

        $response = $this->deleteJson("/api/v1/manager/sponsors/{$sponsor->id_sponsor}");
        $response->assertStatus(409);
        $this->assertDatabaseHas('sponsors', ['id_sponsor' => $sponsor->id_sponsor]);
    }

    public function test_show_eager_loads_club_and_players(): void
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();
        $player = Player::factory()->create();
        $sponsor = Sponsor::factory()->forClub($club)->create();
        $patch = $this->patchJson("/api/v1/manager/sponsors/{$sponsor->id_sponsor}", [
            'player_ids' => [(int) $player->id_player],
        ]);
        $patch->assertOk();

        $response = $this->getJson("/api/v1/manager/sponsors/{$sponsor->id_sponsor}");
        $response->assertOk();
        $this->assertSame($club->id_club, $response->json('data.club.id_club'));
        $this->assertSame($player->id_player, $response->json('data.players.0.id_player'));
    }

    public function test_manager_can_delete_empty_sponsor(): void
    {
        Sanctum::actingAs($this->manager);

        $sponsor = Sponsor::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/sponsors/{$sponsor->id_sponsor}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('sponsors', ['id_sponsor' => $sponsor->id_sponsor]);
    }
}

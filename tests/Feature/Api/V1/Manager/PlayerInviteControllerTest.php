<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use App\Models\PlayerInvite;
use App\Models\PlayerInviteEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerInviteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Invite', 'middle_name' => null, 'last_name' => 'Manager',
        ]);
        $this->player = Player::factory()->playerOnly()->create([
            'first_name' => 'Invite', 'middle_name' => null, 'last_name' => 'Target',
        ]);

        Sanctum::actingAs($this->manager);
    }

    public function test_create_invite_returns_raw_token_once_and_logs_event()
    {
        $response = $this->postJson('/api/v1/manager/player-invites', [
            'player_id' => $this->player->id_player,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'invite' => [
                        'id_invite',
                        'player_id',
                        'expires_at',
                        'invite_token',
                    ],
                ],
            ]);

        $token = $response->json('data.invite.invite_token');
        $inviteId = $response->json('data.invite.id_invite');

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('player_invites', [
            'id_invite' => $inviteId,
            'player_id' => $this->player->id_player,
            'token_hash' => PlayerInvite::hashToken($token),
        ]);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $inviteId,
            'event' => PlayerInviteEvent::EVENT_CREATED,
            'actor_player_id' => $this->manager->id_player,
        ]);

        $expiresAt = (int) $response->json('data.invite.expires_at');
        $this->assertGreaterThan(time() + (3 * 24 * 60 * 60) - 5, $expiresAt);
        $this->assertLessThanOrEqual(time() + (3 * 24 * 60 * 60) + 5, $expiresAt);
    }

    public function test_create_invite_revokes_prior_active_invite()
    {
        $old = PlayerInvite::create([
            'player_id' => $this->player->id_player,
            'token_hash' => PlayerInvite::hashToken('old-token'),
            'expires_at' => time() + 3600,
            'created_by_player_id' => $this->manager->id_player,
        ]);

        $response = $this->postJson('/api/v1/manager/player-invites', [
            'player_id' => $this->player->id_player,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($old->fresh()->revoked_at);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $old->id_invite,
            'event' => PlayerInviteEvent::EVENT_REVOKED,
            'result_code' => 'superseded',
        ]);
        $this->assertSame(1, PlayerInvite::query()->where('player_id', $this->player->id_player)->active()->count());
    }

    public function test_revoke_invite_via_delete()
    {
        $invite = PlayerInvite::create([
            'player_id' => $this->player->id_player,
            'token_hash' => PlayerInvite::hashToken('revoke-me'),
            'expires_at' => time() + 3600,
            'created_by_player_id' => $this->manager->id_player,
        ]);

        $response = $this->deleteJson('/api/v1/manager/player-invites/' . $invite->id_invite);

        $response->assertStatus(200);
        $this->assertNotNull($invite->fresh()->revoked_at);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $invite->id_invite,
            'event' => PlayerInviteEvent::EVENT_REVOKED,
            'result_code' => 'revoked',
        ]);
    }

    public function test_revoke_invite_via_post()
    {
        $invite = PlayerInvite::create([
            'player_id' => $this->player->id_player,
            'token_hash' => PlayerInvite::hashToken('revoke-me-post'),
            'expires_at' => time() + 3600,
            'created_by_player_id' => $this->manager->id_player,
        ]);

        $response = $this->postJson('/api/v1/manager/player-invites/' . $invite->id_invite . '/revoke');

        $response->assertStatus(200);
        $this->assertNotNull($invite->fresh()->revoked_at);
    }

    public function test_invite_endpoints_require_manager_auth()
    {
        Sanctum::actingAs(Player::factory()->playerOnly()->create(['first_name' => 'Not', 'middle_name' => null, 'last_name' => 'Manager']));

        $response = $this->postJson('/api/v1/manager/player-invites', [
            'player_id' => $this->player->id_player,
        ]);

        $response->assertStatus(403);
    }
}

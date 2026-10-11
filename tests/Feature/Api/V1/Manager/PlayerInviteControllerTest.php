<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
use App\Models\PlayerInvite;
use App\Models\PlayerInviteEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerInviteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'first_name' => 'Invite', 'middle_name' => null, 'last_name' => 'Manager',
        ]);
        $this->player = User::factory()->playerOnly()->create([
            'first_name' => 'Invite', 'middle_name' => null, 'last_name' => 'Target',
        ]);

        Sanctum::actingAs($this->manager);
    }

    public function test_create_invite_returns_raw_token_once_and_logs_event()
    {
        $response = $this->postJson('/api/v1/manager/player-invites', [
            'player_user_id' => $this->player->id_user,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'invite' => [
                        'id_invite',
                        'player_user_id',
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
            'player_user_id' => $this->player->id_user,
            'token_hash' => PlayerInvite::hashToken($token),
        ]);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $inviteId,
            'event' => PlayerInviteEvent::EVENT_CREATED,
            'actor_manager_user_id' => $this->manager->id_user,
        ]);

        $expiresAt = (int) $response->json('data.invite.expires_at');
        $this->assertGreaterThan(time() + (3 * 24 * 60 * 60) - 5, $expiresAt);
        $this->assertLessThanOrEqual(time() + (3 * 24 * 60 * 60) + 5, $expiresAt);
    }

    public function test_create_invite_revokes_prior_active_invite()
    {
        $old = PlayerInvite::create([
            'player_user_id' => $this->player->id_user,
            'token_hash' => PlayerInvite::hashToken('old-token'),
            'expires_at' => time() + 3600,
            'created_by_manager_user_id' => $this->manager->id_user,
        ]);

        $response = $this->postJson('/api/v1/manager/player-invites', [
            'player_user_id' => $this->player->id_user,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($old->fresh()->revoked_at);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $old->id_invite,
            'event' => PlayerInviteEvent::EVENT_REVOKED,
            'result_code' => 'superseded',
        ]);
        $this->assertSame(1, PlayerInvite::query()->where('player_user_id', $this->player->id_user)->active()->count());
    }

    public function test_revoke_invite_via_delete()
    {
        $invite = PlayerInvite::create([
            'player_user_id' => $this->player->id_user,
            'token_hash' => PlayerInvite::hashToken('revoke-me'),
            'expires_at' => time() + 3600,
            'created_by_manager_user_id' => $this->manager->id_user,
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
            'player_user_id' => $this->player->id_user,
            'token_hash' => PlayerInvite::hashToken('revoke-me-post'),
            'expires_at' => time() + 3600,
            'created_by_manager_user_id' => $this->manager->id_user,
        ]);

        $response = $this->postJson('/api/v1/manager/player-invites/' . $invite->id_invite . '/revoke');

        $response->assertStatus(200);
        $this->assertNotNull($invite->fresh()->revoked_at);
    }

    public function test_invite_endpoints_require_manager_auth()
    {
        Sanctum::actingAs(User::factory()->playerOnly()->create(['first_name' => 'Not', 'middle_name' => null, 'last_name' => 'Manager']));

        $response = $this->postJson('/api/v1/manager/player-invites', [
            'player_user_id' => $this->player->id_user,
        ]);

        $response->assertStatus(403);
    }
}

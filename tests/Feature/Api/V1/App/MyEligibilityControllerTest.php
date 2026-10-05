<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Eligibility;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyEligibilityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/app/my/eligibility/status')->assertUnauthorized();
        $this->getJson('/api/v1/app/my/eligibility/approved')->assertUnauthorized();
        $this->getJson('/api/v1/app/my/eligibility/latest')->assertUnauthorized();
        $this->patchJson('/api/v1/app/my/eligibility/pending')->assertUnauthorized();
        $this->postJson('/api/v1/app/my/eligibility/discard')->assertUnauthorized();
        $this->postJson('/api/v1/app/my/eligibility/pending/student-proof', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/app/my/eligibility/pending/student-proof/1')->assertUnauthorized();
        $this->postJson('/api/v1/app/my/eligibility/pending/portrait-photo', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/app/my/eligibility/pending/portrait-photo/1')->assertUnauthorized();
    }

    public function test_status_returns_latest_and_overall_counts(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'approved', 'inserted_at' => 10]);
        Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'rejected', 'inserted_at' => 20]);
        Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'discarded', 'inserted_at' => 30]);

        $response = $this->getJson('/api/v1/app/my/eligibility/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'latest' => 'discarded',
                    'overall' => [
                        'approved' => 1,
                        'pending' => 0,
                        'discarded' => 1,
                        'rejected' => 1,
                    ],
                ],
            ]);
    }

    public function test_latest_returns_404_when_none(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/my/eligibility/latest')->assertNotFound();
    }

    public function test_latest_returns_latest_row_any_status(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'approved', 'inserted_at' => 10]);
        $latest = Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'pending', 'inserted_at' => 20]);

        $this->getJson('/api/v1/app/my/eligibility/latest')
            ->assertOk()
            ->assertJsonPath('data.id_eligibility', $latest->id_eligibility);
    }

    public function test_approved_returns_last_approved_only(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'approved', 'reviewed_at' => 10]);
        $latestApproved = Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'approved', 'reviewed_at' => 20]);
        Eligibility::factory()->create(['player_id' => $player->id_player, 'review_status' => 'rejected', 'reviewed_at' => 30]);

        $this->getJson('/api/v1/app/my/eligibility/approved')
            ->assertOk()
            ->assertJsonPath('data.id_eligibility', $latestApproved->id_eligibility);
    }

    public function test_pending_patch_creates_empty_pending_row(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->patchJson('/api/v1/app/my/eligibility/pending', []);

        $response->assertStatus(201)
            ->assertJsonPath('data.player_id', $player->id_player)
            ->assertJsonPath('data.review_status', 'pending');

        $this->assertDatabaseHas('eligibilities', [
            'player_id' => $player->id_player,
            'review_status' => 'pending',
        ]);
    }

    public function test_pending_patch_called_twice_does_not_create_two_pending_rows(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $first = $this->patchJson('/api/v1/app/my/eligibility/pending', [])
            ->assertStatus(201)
            ->json('data.id_eligibility');

        $second = $this->patchJson('/api/v1/app/my/eligibility/pending', [])
            ->assertOk()
            ->json('data.id_eligibility');

        $this->assertSame($first, $second);
        $this->assertSame(
            1,
            Eligibility::where('player_id', $player->id_player)->where('review_status', 'pending')->count()
        );
    }

    public function test_pending_patch_patches_only_sent_fields_and_sets_resubmitted_at(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $pending = Eligibility::factory()->create([
            'player_id' => $player->id_player,
            'review_status' => 'pending',
            'first_name' => null,
            'last_name' => null,
            'resubmitted_at' => null,
        ]);

        $this->patchJson('/api/v1/app/my/eligibility/pending', [
            'first_name' => 'John',
        ])->assertOk();

        $pending->refresh();
        $this->assertSame('John', $pending->first_name);
        $this->assertNull($pending->last_name);
        $this->assertNotNull($pending->resubmitted_at);
    }

    public function test_pending_rejects_duplicate_id_when_pending_exists(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->patchJson('/api/v1/app/my/eligibility/pending', [])->assertStatus(201);

        $source = Eligibility::factory()->create([
            'player_id' => $player->id_player,
            'review_status' => 'approved',
        ]);

        $this->patchJson('/api/v1/app/my/eligibility/pending?duplicate_id=' . $source->id_eligibility, [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['duplicate_id']]);
    }

    public function test_pending_patch_duplicate_id_copies_only_from_same_player(): void
    {
        $player = Player::factory()->playerOnly()->create();
        $other = Player::factory()->playerOnly()->create();

        $sourceOther = Eligibility::factory()->create([
            'player_id' => $other->id_player,
            'review_status' => 'approved',
            'first_name' => 'Other',
        ]);

        Sanctum::actingAs($player);

        $this->patchJson('/api/v1/app/my/eligibility/pending?duplicate_id=' . $sourceOther->id_eligibility, [])
            ->assertStatus(201)
            ->assertJsonPath('data.first_name', null);
    }

    public function test_pending_validates_duplicate_id_query_param_must_be_integer(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->patchJson('/api/v1/app/my/eligibility/pending?duplicate_id=abc', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['duplicate_id']]);
    }

    public function test_pending_ignores_duplicate_id_if_not_found_and_creates_pending(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->patchJson('/api/v1/app/my/eligibility/pending?duplicate_id=999999', [])
            ->assertStatus(201)
            ->assertJsonPath('data.review_status', 'pending');
    }

    public function test_pending_validates_event_ids_exist(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->patchJson('/api/v1/app/my/eligibility/pending', [
            'event_ids' => [999999],
        ])->assertStatus(422);
    }

    public function test_discard_discards_pending_if_exists(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $pending = Eligibility::factory()->create([
            'player_id' => $player->id_player,
            'review_status' => 'pending',
        ]);

        $this->postJson('/api/v1/app/my/eligibility/discard')
            ->assertOk()
            ->assertJson(['success' => true]);

        $pending->refresh();
        $this->assertSame('discarded', $pending->review_status);
    }
}

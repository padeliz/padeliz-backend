<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\Challenge\ChallengeSessionStatus;
use App\Enums\Challenge\ChallengeSessionType;
use App\Models\Challenge\ChallengeSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ChallengeControllerTest extends TestCase
{
    use ActsAsChallengeManager;
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function syncPayload(ChallengeSession $session, array $overrides = []): array
    {
        return array_merge([
            'id_challenge_session' => $session->id_challenge_session,
            'type' => $session->type->value,
            'status' => $session->status->value,
            'title' => $session->title,
            'bottom_text' => $session->bottom_text,
            'started_at' => (int) $session->started_at,
            'completed_at' => $session->completed_at !== null ? (int) $session->completed_at : null,
            'inserted_at' => (int) $session->inserted_at,
            'state_json' => $session->state_json ?? [],
            'state_sequence' => (int) $session->state_sequence,
        ], $overrides);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/manager/challenges')->assertUnauthorized();
        $this->postJson('/api/v1/manager/challenges', [
            'type' => ChallengeSessionType::KingOfTheCourt->value,
        ])->assertUnauthorized();
    }

    public function test_create_starts_active_session_with_empty_state(): void
    {
        $this->actingAsManager();

        $response = $this->postJson('/api/v1/manager/challenges', [
            'type' => ChallengeSessionType::KingOfTheCourt->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', ChallengeSessionStatus::Active->value)
            ->assertJsonPath('data.type', ChallengeSessionType::KingOfTheCourt->value)
            ->assertJsonPath('data.state_sequence', 0)
            ->assertJsonStructure(['data' => ['inserted_at']]);

        $title = $response->json('data.title');
        $this->assertStringStartsWith('King of the Court –', $title);
        $this->assertSame($title, $response->json('data.bottom_text'));

        $session = ChallengeSession::query()->firstOrFail();
        $this->assertSame(0, (int) $session->state_sequence);
        $this->assertIsArray($session->state_json);
        $this->assertSame([], $session->state_json['waiting']);
        $this->assertSame($title, $session->bottom_text);
    }

    public function test_create_smash_seeds_bottom_text_equal_to_title(): void
    {
        $this->actingAsManager();

        $response = $this->postJson('/api/v1/manager/challenges', [
            'type' => ChallengeSessionType::SmashContest->value,
        ]);

        $response->assertCreated();
        $title = $response->json('data.title');
        $this->assertStringStartsWith('Smash Contest –', $title);
        $this->assertSame($title, $response->json('data.bottom_text'));
    }

    public function test_create_returns_409_when_active_exists(): void
    {
        $this->actingAsManager();
        ChallengeSession::factory()->kingOfTheCourt()->create([
            'status' => ChallengeSessionStatus::Active,
        ]);

        $this->postJson('/api/v1/manager/challenges', [
            'type' => ChallengeSessionType::KingOfTheCourt->value,
        ])->assertStatus(409);
    }

    public function test_list_filters_by_type_and_status(): void
    {
        $this->actingAsManager();
        ChallengeSession::factory()->kingOfTheCourt()->create(['status' => ChallengeSessionStatus::Active]);
        ChallengeSession::factory()->smashContest()->completed()->create();

        $this->getJson('/api/v1/manager/challenges?type=smash_contest&status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_session(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->getJson('/api/v1/manager/challenges/' . $session->id_challenge_session)
            ->assertOk()
            ->assertJsonPath('data.id_challenge_session', $session->id_challenge_session)
            ->assertJsonPath('data.state_sequence', 0)
            ->assertJsonPath('data.inserted_at', (int) $session->inserted_at)
            ->assertJsonPath('data.bottom_text', $session->bottom_text);
    }

    public function test_state_returns_state_json_and_sequence(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create([
            'state_json' => ['waiting' => [], 'court_count' => 0],
            'state_sequence' => 3,
        ]);

        $this->getJson('/api/v1/manager/challenges/' . $session->id_challenge_session . '/state')
            ->assertOk()
            ->assertJsonPath('data.state_sequence', 3)
            ->assertJsonPath('data.state_json.court_count', 0)
            ->assertJsonPath('data.session.id_challenge_session', $session->id_challenge_session)
            ->assertJsonPath('data.session.inserted_at', (int) $session->inserted_at)
            ->assertJsonPath('data.session.state_sequence', 3)
            ->assertJsonPath('data.session.bottom_text', $session->bottom_text);
    }

    public function test_sync_updates_existing_row_with_full_body(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create(['state_sequence' => 0]);
        $after = ['waiting' => [['team_name' => 'Alpha']], 'court_count' => 0];

        $response = $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'title' => 'Synced Title For Test Session',
                'bottom_text' => 'Synced Bottom Text For Session',
                'state_json' => $after,
                'state_sequence' => 7,
                'completed_at' => null,
            ])
        );

        $response->assertOk()
            ->assertJsonPath('data.state_sequence', 7)
            ->assertJsonPath('data.title', 'Synced Title For Test Session')
            ->assertJsonPath('data.bottom_text', 'Synced Bottom Text For Session');

        $session->refresh();
        $this->assertSame(7, (int) $session->state_sequence);
        $this->assertSame($after, $session->state_json);
        $this->assertSame('Synced Title For Test Session', $session->title);
        $this->assertSame('Synced Bottom Text For Session', $session->bottom_text);
    }

    public function test_sync_inserts_when_row_missing_with_same_pk(): void
    {
        $this->actingAsManager();
        $id = 4242;
        $insertedAt = time() - 1000;
        $stateJson = ['waiting' => [], 'court_count' => 0];

        $this->assertNull(ChallengeSession::query()->find($id));

        $response = $this->postJson('/api/v1/manager/challenges/' . $id . '/sync', [
            'id_challenge_session' => $id,
            'type' => ChallengeSessionType::SmashContest->value,
            'status' => ChallengeSessionStatus::Active->value,
            'title' => 'Resurrected Smash Contest Title',
            'bottom_text' => 'Resurrected Smash Contest Bottom',
            'started_at' => $insertedAt,
            'completed_at' => null,
            'inserted_at' => $insertedAt,
            'state_json' => $stateJson,
            'state_sequence' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id_challenge_session', $id)
            ->assertJsonPath('data.type', ChallengeSessionType::SmashContest->value)
            ->assertJsonPath('data.state_sequence', 5)
            ->assertJsonPath('data.inserted_at', $insertedAt);

        $row = ChallengeSession::query()->findOrFail($id);
        $this->assertSame($insertedAt, (int) $row->inserted_at);
        $this->assertSame(5, (int) $row->state_sequence);
    }

    public function test_sync_preserves_client_inserted_at_on_update_and_resurrection(): void
    {
        $this->actingAsManager();
        $originalInserted = time() - 5000;
        $session = ChallengeSession::factory()->create([
            'inserted_at' => $originalInserted,
        ]);

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'inserted_at' => $originalInserted,
                'state_sequence' => 2,
                'title' => 'Preserve Inserted At On Update XX',
            ])
        )->assertOk();

        $session->refresh();
        $this->assertSame($originalInserted, (int) $session->inserted_at);

        $id = $session->id_challenge_session;
        $session->delete();

        $this->postJson('/api/v1/manager/challenges/' . $id . '/sync', [
            'id_challenge_session' => $id,
            'type' => ChallengeSessionType::KingOfTheCourt->value,
            'status' => ChallengeSessionStatus::Active->value,
            'title' => 'Preserve Inserted At On Insert XX',
            'bottom_text' => 'Preserve Inserted At On Insert XX',
            'started_at' => $originalInserted,
            'completed_at' => null,
            'inserted_at' => $originalInserted,
            'state_json' => ['waiting' => []],
            'state_sequence' => 3,
        ])->assertOk();

        $row = ChallengeSession::query()->findOrFail($id);
        $this->assertSame($originalInserted, (int) $row->inserted_at);
    }

    public function test_sync_sets_updated_at_to_now(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create([
            'updated_at' => time() - 10_000,
        ]);
        $before = time();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'state_sequence' => 1,
                'title' => 'Updated At Should Refresh Title X',
            ])
        )->assertOk();

        $session->refresh();
        $this->assertGreaterThanOrEqual($before, (int) $session->updated_at);
    }

    public function test_sync_rejects_body_id_mismatch_with_path(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'id_challenge_session' => $session->id_challenge_session + 1,
                'title' => 'Body Id Must Match Path Id Here',
            ])
        )->assertStatus(422);
    }

    public function test_sync_rejects_missing_inserted_at(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();
        $payload = $this->syncPayload($session, [
            'title' => 'Missing Inserted At Should Fail X',
        ]);
        unset($payload['inserted_at']);

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $payload
        )->assertStatus(422);
    }

    public function test_sync_accepts_completed_at_null_for_active(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'status' => ChallengeSessionStatus::Active->value,
                'completed_at' => null,
                'title' => 'Active With Null Completed At XXX',
                'state_sequence' => 1,
            ])
        )->assertOk();

        $session->refresh();
        $this->assertNull($session->completed_at);
        $this->assertSame(ChallengeSessionStatus::Active, $session->status);
    }

    public function test_sync_rejects_type_change_on_update(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->kingOfTheCourt()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'type' => ChallengeSessionType::SmashContest->value,
                'title' => 'Cannot Change Type On Update XXX',
            ])
        )->assertStatus(422);
    }

    public function test_sync_accepts_type_on_insert_resurrection(): void
    {
        $this->actingAsManager();
        $id = 5555;

        $this->postJson('/api/v1/manager/challenges/' . $id . '/sync', [
            'id_challenge_session' => $id,
            'type' => ChallengeSessionType::SmashContest->value,
            'status' => ChallengeSessionStatus::Completed->value,
            'title' => 'Insert Resurrection With Type XXXX',
            'bottom_text' => 'Insert Resurrection With Type XXXX',
            'started_at' => time() - 100,
            'completed_at' => time(),
            'inserted_at' => time() - 100,
            'state_json' => ['waiting' => []],
            'state_sequence' => 1,
        ])->assertOk()
            ->assertJsonPath('data.type', ChallengeSessionType::SmashContest->value);
    }

    public function test_sync_reactivate_rejected_when_other_active_exists(): void
    {
        $this->actingAsManager();
        ChallengeSession::factory()->kingOfTheCourt()->create([
            'status' => ChallengeSessionStatus::Active,
        ]);
        $completed = ChallengeSession::factory()->kingOfTheCourt()->completed()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $completed->id_challenge_session . '/sync',
            $this->syncPayload($completed, [
                'status' => ChallengeSessionStatus::Active->value,
                'completed_at' => null,
                'title' => 'Reactivate Blocked By Other Active',
                'state_sequence' => 1,
            ])
        )->assertStatus(409);
    }

    public function test_sync_rejects_title_shorter_than_20_characters(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, ['title' => 'Too Short Title'])
        )->assertStatus(422);
    }

    public function test_sync_rejects_title_longer_than_50_characters(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'title' => str_repeat('a', 51),
            ])
        )->assertStatus(422);
    }

    public function test_sync_accepts_title_within_20_to_50_characters(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();
        $title = str_repeat('b', 50);

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'title' => $title,
                'state_sequence' => 1,
            ])
        )->assertOk()->assertJsonPath('data.title', $title);
    }

    public function test_sync_rejects_bottom_text_shorter_than_20_characters(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, ['bottom_text' => 'Too Short Bottom'])
        )->assertStatus(422);
    }

    public function test_sync_rejects_bottom_text_longer_than_45_characters(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'bottom_text' => str_repeat('c', 46),
            ])
        )->assertStatus(422);
    }

    public function test_sync_rejects_missing_bottom_text(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();
        $payload = $this->syncPayload($session);
        unset($payload['bottom_text']);

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $payload
        )->assertStatus(422);
    }

    public function test_sync_accepts_bottom_text_within_20_to_45_characters(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();
        $bottomText = str_repeat('d', 20);

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'bottom_text' => $bottomText,
                'state_sequence' => 1,
            ])
        )->assertOk()->assertJsonPath('data.bottom_text', $bottomText);
    }

    public function test_sync_overwrites_with_arbitrary_state_sequence_without_409(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create(['state_sequence' => 50]);

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'state_sequence' => 3,
                'state_json' => ['waiting' => [], 'rewound' => true],
                'title' => 'Arbitrary Sequence Still Succeeds',
            ])
        )->assertOk()->assertJsonPath('data.state_sequence', 3);

        $session->refresh();
        $this->assertSame(3, (int) $session->state_sequence);
    }

    public function test_sync_succeeds_without_actions_key_and_actions_table_gone(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();

        $this->assertFalse(Schema::hasTable('challenge_session_actions'));

        $this->postJson(
            '/api/v1/manager/challenges/' . $session->id_challenge_session . '/sync',
            $this->syncPayload($session, [
                'state_sequence' => 1,
                'title' => 'No Actions Key Required Anymore',
            ])
        )->assertOk();
    }

    public function test_former_action_list_routes_return_404(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();
        $id = $session->id_challenge_session;

        $this->getJson("/api/v1/manager/challenges/{$id}/actions")->assertNotFound();
        $this->getJson('/api/v1/manager/challenge-session-actions')->assertNotFound();
    }

    public function test_removed_live_mutator_routes_return_404(): void
    {
        $this->actingAsManager();
        $session = ChallengeSession::factory()->create();
        $id = $session->id_challenge_session;

        $this->postJson("/api/v1/manager/challenges/{$id}/undo")->assertNotFound();
        $this->postJson("/api/v1/manager/challenges/{$id}/complete")->assertNotFound();
        $this->getJson("/api/v1/manager/challenges/{$id}/kotc/live")->assertNotFound();
        $this->postJson("/api/v1/manager/challenges/{$id}/smash/waiting-players", ['name' => 'X'])->assertNotFound();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\PlayerCustomFieldType;
use App\Models\Division;
use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventParticipationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $event;

    private $division;

    private $eventDivision;

    private $player;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create([
            'name' => 'Test PadelEvent',
            'date' => '2026-06-15',
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
        ]);

        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'division_title' => $this->division->name,
        ]);

        $this->player = Player::factory()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Player',
        ]);
    }

    public function test_manager_can_view_participations_for_edition()
    {
        Sanctum::actingAs($this->manager);

        EventParticipation::factory()->count(5)->create([
            'event_id' => $this->event->id_event,
        ]);

        $response = $this->getJson('/api/v1/manager/event-participations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_participation',
                        'event_id',
                        'player_id',
                        'event_division_id',
                        'absence_reason',
                        'ban_reason',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_participation()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response = $this->getJson("/api/v1/manager/event-participations/{$eventParticipation->id_participation}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation details retrieved successfully',
                'data' => [
                    'id_participation' => $eventParticipation->id_participation,
                    'event_id' => $this->event->id_event,
                    'player_id' => $this->player->id_player,
                    'event_division_id' => $this->eventDivision->id_event_division,
                ],
            ]);
        $this->assertArrayNotHasKey('is_minor', $response->json('data'));
        $this->assertArrayNotHasKey('is_student', $response->json('data'));
        $this->assertArrayNotHasKey('discount', $response->json('data'));
        $this->assertArrayNotHasKey('payment', $response->json('data'));
    }

    public function test_manager_can_create_participation()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipationData = [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ];

        $response = $this->postJson('/api/v1/manager/event-participations', $eventParticipationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Participation created successfully',
                'data' => [
                    'event_id' => $this->event->id_event,
                    'player_id' => $this->player->id_player,
                    'event_division_id' => $this->eventDivision->id_event_division,
                ],
            ]);
        $this->assertArrayNotHasKey('is_minor', $response->json('data'));
        $this->assertArrayNotHasKey('payment', $response->json('data'));

        $this->assertDatabaseHas('event_participations', [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
    }

    public function test_manager_can_update_participation()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $updateData = [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'sort_order' => 1,
            'is_collecting_points' => true,
        ];

        $response = $this->putJson("/api/v1/manager/event-participations/{$eventParticipation->id_participation}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation updated successfully',
                'data' => [
                    'id_participation' => $eventParticipation->id_participation,
                    'sort_order' => 1,
                    'is_collecting_points' => true,
                ],
            ]);
        $this->assertArrayNotHasKey('is_minor', $response->json('data'));
    }

    public function test_manager_can_partially_update_participation()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_collecting_points' => true,
        ]);

        $response = $this->patchJson("/api/v1/manager/event-participations/{$eventParticipation->id_participation}", [
            'is_collecting_points' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation partially updated successfully',
                'data' => [
                    'is_collecting_points' => false,
                ],
            ]);
        $this->assertArrayNotHasKey('is_minor', $response->json('data'));
    }

    public function test_manager_cannot_update_participation_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/event-participations/{$eventParticipation->id_participation}", [
            'absence_reason' => 'SICK',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'player_id', 'event_division_id', 'sort_order', 'is_collecting_points']);

        $response = $this->putJson("/api/v1/manager/event-participations/{$eventParticipation->id_participation}", [
            'event_id' => '',
            'player_id' => '',
            'event_division_id' => '',
            'sort_order' => '',
            'is_collecting_points' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'player_id', 'event_division_id', 'sort_order', 'is_collecting_points']);
    }

    public function test_manager_can_delete_participation()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/event-participations/{$eventParticipation->id_participation}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation deleted successfully',
            ]);

        $this->assertDatabaseMissing('event_participations', [
            'id_participation' => $eventParticipation->id_participation,
        ]);
    }

    public function test_manager_can_bulk_delete_participations()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipations = EventParticipation::factory()->count(3)->create([
            'event_id' => $this->event->id_event,
        ]);

        $response = $this->deleteJson('/api/v1/manager/event-participations/bulk', [
            'participation_ids' => $eventParticipations->pluck('id_participation')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participations deleted successfully',
                'data' => [
                    'total_successful' => 3,
                    'total_failed' => 0,
                    'total_requested' => 3,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'successful' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);

        // Verify all participations are deleted
        foreach ($eventParticipations as $eventParticipation) {
            $this->assertDatabaseMissing('event_participations', ['id_participation' => $eventParticipation->id_participation]);
        }
    }

    public function test_manager_cannot_bulk_delete_participations_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/event-participations/bulk', [
            'participation_ids' => [99999, 99998],
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'All participations failed to delete',
                'errors' => [
                    'total_successful' => 0,
                    'total_failed' => 2,
                    'total_requested' => 2,
                ],
            ])
            ->assertJsonStructure([
                'errors' => [
                    'successful',
                    'failed' => [
                        '*' => [
                            'id',
                            'reason',
                        ],
                    ],
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);
    }

    public function test_manager_cannot_bulk_delete_participations_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/event-participations/bulk', [
            'participation_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_participations_without_participation_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/event-participations/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_participations_with_invalid_participation_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/event-participations/bulk', [
            'participation_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_create_duplicate_participation()
    {
        Sanctum::actingAs($this->manager);

        // Create first participation
        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        // Try to create duplicate
        $duplicateData = [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ];

        $response = $this->postJson('/api/v1/manager/event-participations', $duplicateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This player is already participating in this edition division',
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_participation()
    {
        Sanctum::actingAs($this->manager);

        $player2 = Player::factory()->create();

        // Create two different participations
        $eventParticipation1 = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $eventParticipation2 = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $player2->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/event-participations/{$eventParticipation2->id_participation}", [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'sort_order' => 0,
            'is_collecting_points' => true,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This player is already participating in this edition division',
            ]);
    }

    public function test_participation_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-participations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'player_id', 'event_division_id']);
    }

    public function test_participation_creation_validation_invalid_player()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-participations', [
            'event_id' => $this->event->id_event,
            'player_id' => 99999, // Non-existent player
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_participation_creation_validation_invalid_division()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-participations', [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => 99999, // Non-existent division
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);
    }

    public function test_participation_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
        ]);

        $response = $this->putJson("/api/v1/manager/event-participations/{$eventParticipation->id_participation}", [
            'event_id' => $this->event->id_event,
            'player_id' => 99999, // Non-existent player
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_participation_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/event-participations');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_participation_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/event-participations');

        $response->assertStatus(403);
    }

    public function test_participation_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/event-participations/99999');

        $response->assertStatus(404);
    }

    public function test_participation_pagination()
    {
        Sanctum::actingAs($this->manager);

        EventParticipation::factory()->count(25)->create([
            'event_id' => $this->event->id_event,
        ]);

        $response = $this->getJson('/api/v1/manager/event-participations?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_participation',
                        'event_id',
                        'player_id',
                        'event_division_id',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_participation_with_optional_fields()
    {
        Sanctum::actingAs($this->manager);

        $eventParticipationData = [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            // Not providing optional fields
        ];

        $response = $this->postJson('/api/v1/manager/event-participations', $eventParticipationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Participation created successfully',
            ]);

        $this->assertDatabaseHas('event_participations', [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'sort_order' => 0,
            'is_collecting_points' => 1,
        ]);
    }

    public function test_index_with_event_id_filter_includes_player_history(): void
    {
        Sanctum::actingAs($this->manager);

        $pastEdition = PadelEvent::factory()->create(['date' => '2026-01-01']);
        $pastDivision = Division::factory()->create(['color' => '#ff5500']);
        $pastEd = EventDivision::factory()->create([
            'event_id' => $pastEdition->id_event,
            'division_id' => $pastDivision->id_division,
            'division_title' => 'History ED',
        ]);
        EventParticipation::factory()->create([
            'event_id' => $pastEdition->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $pastEd->id_event_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $response = $this->getJson(
            '/api/v1/manager/event-participations?filters[event_id]=' . $this->event->id_event
        );

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('player_id', $this->player->id_player);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('player_history', $row);
        $this->assertSame(1, $row['player_history']['participated_count']);
        $this->assertSame('History ED', $row['player_history']['last_played']['division_title']);
        $this->assertSame('#ff5500', $row['player_history']['last_played']['division_color']);
        $this->assertArrayNotHasKey('winner', $row['player_history']);
        $this->assertArrayHasKey('played_recent', $row['player_history']);
        $this->assertArrayHasKey('participated_tooltip', $row['player_history']);
        $this->assertArrayHasKey('banned_recent', $row['player_history']);
        $this->assertArrayHasKey('absented_recent', $row['player_history']);
        $this->assertCount(1, $row['player_history']['played_recent']);
        $this->assertSame('History ED', $row['player_history']['played_recent'][0]['division_title']);
        $this->assertSame('#ff5500', $row['player_history']['played_recent'][0]['division_color']);
        $this->assertSame('entry', $row['player_history']['participated_tooltip']['lines'][0]['type']);
    }

    public function test_index_without_event_id_filter_omits_player_history(): void
    {
        Sanctum::actingAs($this->manager);

        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response = $this->getJson('/api/v1/manager/event-participations');

        $response->assertStatus(200);
        $row = $response->json('data.0');
        $this->assertArrayNotHasKey('player_history', $row);
    }

    public function test_show_create_update_omit_player_history(): void
    {
        Sanctum::actingAs($this->manager);

        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $show = $this->getJson("/api/v1/manager/event-participations/{$participation->id_participation}");
        $show->assertStatus(200);
        $this->assertArrayNotHasKey('player_history', $show->json('data'));

        $otherPlayer = Player::factory()->create();
        $create = $this->postJson('/api/v1/manager/event-participations', [
            'event_id' => $this->event->id_event,
            'player_id' => $otherPlayer->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $create->assertStatus(201);
        $this->assertArrayNotHasKey('player_history', $create->json('data'));

        $update = $this->putJson("/api/v1/manager/event-participations/{$participation->id_participation}", [
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'sort_order' => 0,
            'is_collecting_points' => true,
        ]);
        $update->assertStatus(200);
        $this->assertArrayNotHasKey('player_history', $update->json('data'));

        $reorder = $this->patchJson('/api/v1/manager/event-participations/reorder', [
            'participations' => [
                [
                    'id_participation' => $participation->id_participation,
                    'event_division_id' => $this->eventDivision->id_event_division,
                    'sort_order' => 0,
                ],
            ],
        ]);
        $reorder->assertOk();
        $reorderData = $reorder->json('data');
        if (is_array($reorderData) && isset($reorderData[0])) {
            $this->assertArrayNotHasKey('player_history', $reorderData[0]);
        } elseif (is_array($reorderData)) {
            $this->assertArrayNotHasKey('player_history', $reorderData);
        }

        $toDelete = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $bulk = $this->deleteJson('/api/v1/manager/event-participations/bulk', [
            'participation_ids' => [$toDelete->id_participation],
        ]);
        $bulk->assertOk();
        $this->assertArrayNotHasKey('player_history', $bulk->json('data') ?? []);
    }

    public function test_index_with_event_id_includes_flags_and_event_summary(): void
    {
        Sanctum::actingAs($this->manager);

        $registered = Player::factory()->create();
        $unregistered = Player::factory()->create();
        \App\Models\EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_id' => $registered->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);

        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_id' => $registered->id_player,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_id' => $unregistered->id_player,
        ]);

        $response = $this->getJson(
            '/api/v1/manager/event-participations?filters[event_id]=' . $this->event->id_event
        );

        $response->assertOk()
            ->assertJsonPath('meta.event_summary.registered_not_participating_count', 0)
            ->assertJsonPath('meta.event_summary.participations_locked', false)
            ->assertJsonPath('meta.current_page', 1);

        $rows = collect($response->json('data'));
        $this->assertTrue($rows->contains(fn ($row) => in_array('not_registered', $row['flags'] ?? [], true)));
    }

    public function test_destroy_and_bulk_destroy_blocked_when_lottery_exists(): void
    {
        Sanctum::actingAs($this->manager);

        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $other = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        \App\Models\EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $this->deleteJson('/api/v1/manager/event-participations/' . $participation->id_participation)
            ->assertStatus(409);

        $bulk = $this->deleteJson('/api/v1/manager/event-participations/bulk', [
            'participation_ids' => [$other->id_participation],
        ]);
        $bulk->assertStatus(409);
        $this->assertSame(1, $bulk->json('errors.total_failed'));
    }

    public function test_index_without_custom_field_ids_param_returns_empty_custom_field_values(): void
    {
        Sanctum::actingAs($this->manager);

        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response = $this->getJson(
            '/api/v1/manager/event-participations?filters[event_id]=' . $this->event->id_event
        );

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('player_id', $this->player->id_player);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('custom_field_values', $row);
        $this->assertSame([], $row['custom_field_values']);
    }

    public function test_index_custom_field_ids_without_edition_filter_are_ignored(): void
    {
        Sanctum::actingAs($this->manager);

        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $this->player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Should not appear',
        ]);
        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response = $this->getJson(
            '/api/v1/manager/event-participations?custom_field_ids[]=' . $field->id_player_custom_field
        );

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertArrayNotHasKey('custom_field_values', $row);
    }

    public function test_index_with_edition_filter_and_custom_field_ids_returns_values(): void
    {
        Sanctum::actingAs($this->manager);

        $field = PlayerCustomField::factory()->create([
            'label' => 'Club',
            'type' => PlayerCustomFieldType::Text,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $this->player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Padel Club',
            'inserted_at' => \Carbon\Carbon::parse('2026-06-01', config('app.timezone'))->timestamp,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response = $this->getJson(
            '/api/v1/manager/event-participations'
            . '?filters[event_id]=' . $this->event->id_event
            . '&custom_field_ids[]=' . $field->id_player_custom_field
        );

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('player_id', $this->player->id_player);
        $this->assertNotNull($row);
        $this->assertSame('Club', $row['custom_field_values'][0]['label']);
        $this->assertSame('Padel Club', $row['custom_field_values'][0]['value']);
        $this->assertSame($field->id_player_custom_field, $row['custom_field_values'][0]['field_id']);
        $this->assertArrayHasKey('recent_answers', $row['custom_field_values'][0]);
        $this->assertNotEmpty($row['custom_field_values'][0]['recent_answers']);
        $this->assertSame('Padel Club', $row['custom_field_values'][0]['recent_answers'][0]['value']);
        $this->assertArrayHasKey('inserted_date', $row['custom_field_values'][0]['recent_answers'][0]);
        $this->assertArrayHasKey('edition_name', $row['custom_field_values'][0]['recent_answers'][0]);
    }

    public function test_index_with_unknown_custom_field_ids_returns_empty_values(): void
    {
        Sanctum::actingAs($this->manager);

        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response = $this->getJson(
            '/api/v1/manager/event-participations'
            . '?filters[event_id]=' . $this->event->id_event
            . '&custom_field_ids[]=99999'
        );

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('player_id', $this->player->id_player);
        $this->assertNotNull($row);
        $this->assertSame([], $row['custom_field_values']);
    }

    public function test_index_includes_collecting_aware_pdf_avatar_url(): void
    {
        Sanctum::actingAs($this->manager);

        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_id' => $this->player->id_player,
            'is_collecting_points' => true,
        ]);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_id' => $this->player->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_player . '/false.png',
        ]);

        $miss = $this->getJson(
            '/api/v1/manager/event-participations?filters[event_id]=' . $this->event->id_event
        );
        $miss->assertOk();
        $missRow = collect($miss->json('data'))->firstWhere('id_participation', $participation->id_participation);
        $this->assertNull($missRow['pdf_avatar_url']);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_id' => $this->player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_player . '/true.png',
        ]);

        $response = $this->getJson(
            '/api/v1/manager/event-participations?filters[event_id]=' . $this->event->id_event
        );
        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id_participation', $participation->id_participation);
        $this->assertNotNull($row['pdf_avatar_url']);
    }

    public function test_index_pdf_avatar_fresh_after_cache_warm(): void
    {
        Sanctum::actingAs($this->manager);

        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_id' => $this->player->id_player,
            'is_collecting_points' => true,
        ]);

        $warm = $this->getJson(
            '/api/v1/manager/event-participations?filters[event_id]=' . $this->event->id_event
        )->assertOk();
        $warmRow = collect($warm->json('data'))->firstWhere('id_participation', $participation->id_participation);
        $this->assertNull($warmRow['pdf_avatar_url']);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_id' => $this->player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_player . '/fresh.png',
        ]);

        $again = $this->getJson(
            '/api/v1/manager/event-participations?filters[event_id]=' . $this->event->id_event
        )->assertOk();
        $row = collect($again->json('data'))->firstWhere('id_participation', $participation->id_participation);
        $this->assertNotNull($row['pdf_avatar_url']);
    }

    public function test_show_hydrates_pdf_avatar_url(): void
    {
        Sanctum::actingAs($this->manager);

        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_id' => $this->player->id_player,
            'is_collecting_points' => true,
        ]);
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_id' => $this->player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_player . '/show.png',
        ]);

        $response = $this->getJson("/api/v1/manager/event-participations/{$participation->id_participation}")
            ->assertOk();
        $this->assertNotNull($response->json('data.pdf_avatar_url'));
    }
}

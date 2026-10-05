<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventDivisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $event;

    private $division;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create([
            'name' => 'Test PadelEvent',
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
        ]);
    }

    public function test_manager_can_view_event_divisions()
    {
        Sanctum::actingAs($this->manager);

        EventDivision::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/event-divisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_event_division',
                        'event_id',
                        'division_id',
                        'players_limit',
                        'division_title',
                        'playing_start_time',
                        'playing_end_time',
                        'skill_order',
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

    public function test_manager_can_view_specific_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->getJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_event_division' => $eventDivision->id_event_division,
                    'event_id' => $eventDivision->event_id,
                    'division_id' => $eventDivision->division_id,
                ],
            ]);
    }

    public function test_manager_can_create_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $eventDivisionData = [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'entry_fee' => 55,
            'division_title' => 'Adults Explorers A',
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ];

        $response = $this->postJson('/api/v1/manager/event-divisions', $eventDivisionData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent division created successfully',
            ]);

        $this->assertDatabaseHas('event_divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'entry_fee' => 55,
            'division_title' => 'Adults Explorers A',
        ]);
    }

    public function test_manager_can_update_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        $updateData = [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'entry_fee' => 55,
            'division_title' => 'Adults Explorers B',
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '18:00:00',
        ];

        $response = $this->putJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent division updated successfully',
            ]);

        $this->assertDatabaseHas('event_divisions', [
            'id_event_division' => $eventDivision->id_event_division,
            'division_title' => 'Adults Explorers B',
            'players_limit' => 32,
            'entry_fee' => 55,
        ]);
    }

    public function test_manager_can_partially_update_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        $updateData = [
            'division_title' => 'Adults Explorers C',
            'players_limit' => 24,
            'entry_fee' => 55,
        ];

        $response = $this->patchJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent division partially updated successfully',
            ]);

        $this->assertDatabaseHas('event_divisions', [
            'id_event_division' => $eventDivision->id_event_division,
            'division_title' => 'Adults Explorers C',
            'players_limit' => 24,
            'entry_fee' => 55,
        ]);
    }

    public function test_manager_cannot_update_edition_division_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->putJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'division_id', 'players_limit', 'division_title', 'playing_start_time', 'playing_end_time']);
    }

    public function test_manager_can_delete_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent division deleted successfully',
            ]);

        $this->assertDatabaseMissing('event_divisions', [
            'id_event_division' => $eventDivision->id_event_division,
        ]);
    }

    public function test_manager_cannot_create_duplicate_edition_division()
    {
        Sanctum::actingAs($this->manager);

        // Create first edition division
        EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        // Try to create duplicate with all required fields
        $response = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'entry_fee' => 55,
            'division_title' => 'Adults Explorers B',
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '18:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This division is already assigned to this edition',
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $edition2 = PadelEvent::factory()->create();
        $division2 = Division::factory()->create();

        // Create two different edition divisions
        $eventDivision1 = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        $eventDivision2 = EventDivision::factory()->create([
            'event_id' => $edition2->id_event,
            'division_id' => $division2->id_division,
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/event-divisions/{$eventDivision2->id_event_division}", [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'entry_fee' => 55,
            'division_title' => 'Adults Explorers B',
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '18:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This division is already assigned to this edition',
            ]);
    }

    public function test_edition_division_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-divisions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'division_id', 'players_limit', 'entry_fee', 'playing_start_time', 'playing_end_time'])
            ->assertJsonMissingValidationErrors(['division_title']);
    }

    public function test_create_defaults_division_title_to_division_name(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.division_title', $this->division->name);
    }

    public function test_create_defaults_skill_order_to_next_free_slot(): void
    {
        Sanctum::actingAs($this->manager);

        $first = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);
        $first->assertStatus(201)->assertJsonPath('data.skill_order', 0);

        $second = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);
        $second->assertStatus(201)->assertJsonPath('data.skill_order', 1);

        $explicit = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'skill_order' => 0,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);
        $explicit->assertStatus(201)->assertJsonPath('data.skill_order', 0);
    }

    public function test_create_does_not_create_schedule_row(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('event_division_schedules', [
            'event_division_id' => $response->json('data.id_event_division'),
        ]);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function movedScheduleKeyProvider(): array
    {
        return [
            ['player_opponents_nr'],
            ['player_matches_repeat'],
            ['include_scores'],
            ['has_demonstrative_match'],
            ['allow_replacements'],
            ['points_per_match'],
            ['final_points_target'],
            ['include_final'],
            ['template_version'],
            ['adjust'],
        ];
    }

    /**
     * @dataProvider movedScheduleKeyProvider
     */
    public function test_store_rejects_moved_schedule_keys(string $key): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'division_title' => 'Knobs Division',
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
            $key => 4,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors([$key]);
    }

    /**
     * @dataProvider movedScheduleKeyProvider
     */
    public function test_update_and_patch_reject_moved_schedule_keys(string $key): void
    {
        Sanctum::actingAs($this->manager);

        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
        ]);

        $this->putJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}", [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'division_title' => 'Knobs Division',
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
            $key => 4,
        ])->assertStatus(422)->assertJsonValidationErrors([$key]);

        $this->patchJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}", [
            $key => 4,
        ])->assertStatus(422)->assertJsonValidationErrors([$key]);
    }

    public function test_index_defaults_to_skill_order_then_id(): void
    {
        Sanctum::actingAs($this->manager);

        $third = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'skill_order' => 2,
        ]);
        $first = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'skill_order' => 0,
        ]);
        $second = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'skill_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/manager/event-divisions?filters[event_id]=' . $this->event->id_event);

        $response->assertStatus(200);
        $this->assertSame(
            [
                $first->id_event_division,
                $second->id_event_division,
                $third->id_event_division,
            ],
            array_column($response->json('data'), 'id_event_division')
        );
    }

    public function test_reorder_rewrites_skill_order_from_zero(): void
    {
        Sanctum::actingAs($this->manager);

        $a = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'skill_order' => 0,
        ]);
        $b = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'skill_order' => 1,
        ]);
        $c = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'skill_order' => 2,
        ]);

        $response = $this->patchJson('/api/v1/manager/event-divisions/reorder', [
            'event_id' => $this->event->id_event,
            'divisions' => [
                ['id_event_division' => $a->id_event_division, 'skill_order' => 7],
                ['id_event_division' => $b->id_event_division, 'skill_order' => 3],
                ['id_event_division' => $c->id_event_division, 'skill_order' => 5],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent divisions reordered successfully',
            ]);

        $this->assertSame(
            [
                $b->id_event_division,
                $c->id_event_division,
                $a->id_event_division,
            ],
            array_column($response->json('data'), 'id_event_division')
        );
        $this->assertSame([0, 1, 2], array_column($response->json('data'), 'skill_order'));

        $this->assertSame(0, (int) $b->fresh()->skill_order);
        $this->assertSame(1, (int) $c->fresh()->skill_order);
        $this->assertSame(2, (int) $a->fresh()->skill_order);
    }

    public function test_reorder_requires_every_division_of_the_edition(): void
    {
        Sanctum::actingAs($this->manager);

        $a = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'skill_order' => 0,
        ]);
        EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'skill_order' => 1,
        ]);

        $response = $this->patchJson('/api/v1/manager/event-divisions/reorder', [
            'event_id' => $this->event->id_event,
            'divisions' => [
                ['id_event_division' => $a->id_event_division, 'skill_order' => 0],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'divisions must include every edition division of this edition.',
            ]);
    }

    public function test_reorder_rejects_divisions_from_another_edition(): void
    {
        Sanctum::actingAs($this->manager);

        $mine = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'skill_order' => 0,
        ]);
        $foreign = EventDivision::factory()->create([
            'event_id' => PadelEvent::factory()->create()->id_event,
            'skill_order' => 0,
        ]);

        $response = $this->patchJson('/api/v1/manager/event-divisions/reorder', [
            'event_id' => $this->event->id_event,
            'divisions' => [
                ['id_event_division' => $mine->id_event_division, 'skill_order' => 0],
                ['id_event_division' => $foreign->id_event_division, 'skill_order' => 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'One or more edition divisions do not belong to this edition.',
            ]);
    }

    public function test_reorder_rejected_after_finalize(): void
    {
        Sanctum::actingAs($this->manager);

        $finalized = PadelEvent::factory()->create(['finalized_at' => time()]);
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $finalized->id_event,
            'skill_order' => 0,
        ]);

        $response = $this->patchJson('/api/v1/manager/event-divisions/reorder', [
            'event_id' => $finalized->id_event,
            'divisions' => [
                ['id_event_division' => $eventDivision->id_event_division, 'skill_order' => 0],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'PadelEvent divisions cannot be reordered after finalize.',
            ]);
    }

    public function test_edition_division_creation_allows_overnight_and_end_of_day(): void
    {
        Sanctum::actingAs($this->manager);

        $overnight = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'division_title' => 'Overnight Division',
            'playing_start_time' => '17:00:00',
            'playing_end_time' => '09:00:00',
        ]);

        $overnight->assertStatus(201)
            ->assertJsonPath('data.playing_start_time', '17:00:00')
            ->assertJsonPath('data.playing_end_time', '09:00:00');

        $otherDivision = \App\Models\Division::factory()->create();
        $endOfDay = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $otherDivision->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'division_title' => 'End of day Division',
            'playing_start_time' => '18:00:00',
            'playing_end_time' => '24:00:00',
        ]);

        $endOfDay->assertStatus(201)
            ->assertJsonPath('data.playing_end_time', '24:00:00');
    }

    public function test_edition_division_creation_rejects_invalid_time_shape(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'entry_fee' => 55,
            'division_title' => 'Bad times',
            'playing_start_time' => '25:00:00',
            'playing_end_time' => '18:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['playing_start_time']);
    }

    public function test_edition_division_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $eventDivision = EventDivision::factory()->create();

        $response = $this->putJson("/api/v1/manager/event-divisions/{$eventDivision->id_event_division}", [
            'event_id' => 99999, // Non-existent edition
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_id']);
    }

    public function test_edition_division_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/event-divisions');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_edition_division_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/event-divisions');

        $response->assertStatus(403);
    }

    public function test_edition_division_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/event-divisions/99999');

        $response->assertStatus(404);
    }

    public function test_edition_division_pagination()
    {
        Sanctum::actingAs($this->manager);

        EventDivision::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/event-divisions?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_event_division',
                        'event_id',
                        'division_id',
                        'players_limit',
                        'division_title',
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
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionDivisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $edition;

    private $division;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->edition = Edition::factory()->create([
            'name' => 'Test Edition',
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
        ]);
    }

    public function test_manager_can_view_edition_divisions()
    {
        Sanctum::actingAs($this->manager);

        EditionDivision::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/edition-divisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_edition_division',
                        'edition_id',
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

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_edition_division' => $editionDivision->id_edition_division,
                    'edition_id' => $editionDivision->edition_id,
                    'division_id' => $editionDivision->division_id,
                ],
            ]);
    }

    public function test_manager_can_create_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivisionData = [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'division_title' => 'Adults Explorers A',
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ];

        $response = $this->postJson('/api/v1/manager/edition-divisions', $editionDivisionData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division created successfully',
            ]);

        $this->assertDatabaseHas('edition_divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'division_title' => 'Adults Explorers A',
        ]);
    }

    public function test_manager_can_update_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $updateData = [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'division_title' => 'Adults Explorers B',
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '18:00:00',
        ];

        $response = $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division updated successfully',
            ]);

        $this->assertDatabaseHas('edition_divisions', [
            'id_edition_division' => $editionDivision->id_edition_division,
            'division_title' => 'Adults Explorers B',
            'players_limit' => 32,
        ]);
    }

    public function test_manager_can_partially_update_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $updateData = [
            'division_title' => 'Adults Explorers C',
            'players_limit' => 24,
        ];

        $response = $this->patchJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division partially updated successfully',
            ]);

        $this->assertDatabaseHas('edition_divisions', [
            'id_edition_division' => $editionDivision->id_edition_division,
            'division_title' => 'Adults Explorers C',
            'players_limit' => 24,
        ]);
    }

    public function test_manager_cannot_update_edition_division_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'division_id', 'players_limit', 'division_title', 'playing_start_time', 'playing_end_time']);
    }

    public function test_manager_can_delete_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division deleted successfully',
            ]);

        $this->assertDatabaseMissing('edition_divisions', [
            'id_edition_division' => $editionDivision->id_edition_division,
        ]);
    }

    public function test_manager_cannot_create_duplicate_edition_division()
    {
        Sanctum::actingAs($this->manager);

        // Create first edition division
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        // Try to create duplicate with all required fields
        $response = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
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

        $edition2 = Edition::factory()->create();
        $division2 = Division::factory()->create();

        // Create two different edition divisions
        $editionDivision1 = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $editionDivision2 = EditionDivision::factory()->create([
            'edition_id' => $edition2->id_edition,
            'division_id' => $division2->id_division,
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision2->id_edition_division}", [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
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

        $response = $this->postJson('/api/v1/manager/edition-divisions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'division_id', 'players_limit', 'playing_start_time', 'playing_end_time'])
            ->assertJsonMissingValidationErrors(['division_title']);
    }

    public function test_create_defaults_division_title_to_division_name(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.division_title', $this->division->name);
    }

    public function test_create_defaults_skill_order_to_next_free_slot(): void
    {
        Sanctum::actingAs($this->manager);

        $first = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);
        $first->assertStatus(201)->assertJsonPath('data.skill_order', 0);

        $second = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 16,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);
        $second->assertStatus(201)->assertJsonPath('data.skill_order', 1);

        $explicit = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 16,
            'skill_order' => 0,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);
        $explicit->assertStatus(201)->assertJsonPath('data.skill_order', 0);
    }

    public function test_create_does_not_create_schedule_row(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('edition_division_schedules', [
            'edition_division_id' => $response->json('data.id_edition_division'),
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

        $response = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
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

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'division_title' => 'Knobs Division',
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
            $key => 4,
        ])->assertStatus(422)->assertJsonValidationErrors([$key]);

        $this->patchJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", [
            $key => 4,
        ])->assertStatus(422)->assertJsonValidationErrors([$key]);
    }

    public function test_index_defaults_to_skill_order_then_id(): void
    {
        Sanctum::actingAs($this->manager);

        $third = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'skill_order' => 2,
        ]);
        $first = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'skill_order' => 0,
        ]);
        $second = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'skill_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/manager/edition-divisions?filters[edition_id]=' . $this->edition->id_edition);

        $response->assertStatus(200);
        $this->assertSame(
            [
                $first->id_edition_division,
                $second->id_edition_division,
                $third->id_edition_division,
            ],
            array_column($response->json('data'), 'id_edition_division')
        );
    }

    public function test_reorder_rewrites_skill_order_from_zero(): void
    {
        Sanctum::actingAs($this->manager);

        $a = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'skill_order' => 0,
        ]);
        $b = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'skill_order' => 1,
        ]);
        $c = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'skill_order' => 2,
        ]);

        $response = $this->patchJson('/api/v1/manager/edition-divisions/reorder', [
            'edition_id' => $this->edition->id_edition,
            'divisions' => [
                ['id_edition_division' => $a->id_edition_division, 'skill_order' => 7],
                ['id_edition_division' => $b->id_edition_division, 'skill_order' => 3],
                ['id_edition_division' => $c->id_edition_division, 'skill_order' => 5],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition divisions reordered successfully',
            ]);

        $this->assertSame(
            [
                $b->id_edition_division,
                $c->id_edition_division,
                $a->id_edition_division,
            ],
            array_column($response->json('data'), 'id_edition_division')
        );
        $this->assertSame([0, 1, 2], array_column($response->json('data'), 'skill_order'));

        $this->assertSame(0, (int) $b->fresh()->skill_order);
        $this->assertSame(1, (int) $c->fresh()->skill_order);
        $this->assertSame(2, (int) $a->fresh()->skill_order);
    }

    public function test_reorder_requires_every_division_of_the_edition(): void
    {
        Sanctum::actingAs($this->manager);

        $a = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'skill_order' => 0,
        ]);
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'skill_order' => 1,
        ]);

        $response = $this->patchJson('/api/v1/manager/edition-divisions/reorder', [
            'edition_id' => $this->edition->id_edition,
            'divisions' => [
                ['id_edition_division' => $a->id_edition_division, 'skill_order' => 0],
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

        $mine = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'skill_order' => 0,
        ]);
        $foreign = EditionDivision::factory()->create([
            'edition_id' => Edition::factory()->create()->id_edition,
            'skill_order' => 0,
        ]);

        $response = $this->patchJson('/api/v1/manager/edition-divisions/reorder', [
            'edition_id' => $this->edition->id_edition,
            'divisions' => [
                ['id_edition_division' => $mine->id_edition_division, 'skill_order' => 0],
                ['id_edition_division' => $foreign->id_edition_division, 'skill_order' => 1],
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

        $finalized = Edition::factory()->create(['finalized_at' => time()]);
        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $finalized->id_edition,
            'skill_order' => 0,
        ]);

        $response = $this->patchJson('/api/v1/manager/edition-divisions/reorder', [
            'edition_id' => $finalized->id_edition,
            'divisions' => [
                ['id_edition_division' => $editionDivision->id_edition_division, 'skill_order' => 0],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Edition divisions cannot be reordered after finalize.',
            ]);
    }

    public function test_edition_division_creation_allows_overnight_and_end_of_day(): void
    {
        Sanctum::actingAs($this->manager);

        $overnight = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'division_title' => 'Overnight Division',
            'playing_start_time' => '17:00:00',
            'playing_end_time' => '09:00:00',
        ]);

        $overnight->assertStatus(201)
            ->assertJsonPath('data.playing_start_time', '17:00:00')
            ->assertJsonPath('data.playing_end_time', '09:00:00');

        $otherDivision = \App\Models\Division::factory()->create();
        $endOfDay = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $otherDivision->id_division,
            'players_limit' => 16,
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

        $response = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
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

        $editionDivision = EditionDivision::factory()->create();

        $response = $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", [
            'edition_id' => 99999, // Non-existent edition
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id']);
    }

    public function test_edition_division_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/edition-divisions');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_edition_division_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/edition-divisions');

        $response->assertStatus(403);
    }

    public function test_edition_division_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/edition-divisions/99999');

        $response->assertStatus(404);
    }

    public function test_edition_division_pagination()
    {
        Sanctum::actingAs($this->manager);

        EditionDivision::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/edition-divisions?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_edition_division',
                        'edition_id',
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

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionLottery;
use App\Models\EditionParticipation;
use App\Models\Location;
use App\Models\Player;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $club;

    private $location;

    private $edition;

    private $division;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'name' => 'Test Manager',
            'phone' => '1234567890',
            'google_id' => 'google_manager_123',
            'google_name' => 'Test Manager',
            'google_email' => 'manager@example.com',
            'google_avatar' => 'https://example.com/manager-avatar.jpg',
            'user_types' => ['player', 'manager'],
        ]);

        $this->club = Club::factory()->create([
            'name' => 'Test Club',
            'city' => 'Test City',
        ]);

        $this->location = Location::factory()->create([
            'club_id' => $this->club->id_club,
            'name' => 'Test Location',
            'address' => 'Test Address',
        ]);

        $this->edition = Edition::factory()->create([
            'location_id' => $this->location->id_location,
            'name' => 'Test Edition',
            'date' => now()->addDays(30),
            'entry_fee' => 50.00,
            'students_entry_fee' => 25.00,
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
            'color' => '#FF0000',
            'is_students_only' => false,
        ]);
    }

    public function test_manager_can_view_editions()
    {
        Sanctum::actingAs($this->manager);

        // Clear existing editions and create exactly 5
        Edition::query()->delete();
        Edition::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/editions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_edition',
                        'location_id',
                        'name',
                        'date',
                        'entry_fee',
                        'students_entry_fee',
                        'arriving_time',
                        'closing_time',
                        'inserted_at',
                        'updated_at',
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

    public function test_manager_can_view_specific_edition()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_edition',
                    'location_id',
                    'name',
                    'date',
                    'entry_fee',
                    'students_entry_fee',
                    'arriving_time',
                    'closing_time',
                    'inserted_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Edition details retrieved successfully',
                'data' => [
                    'id_edition' => $this->edition->id_edition,
                    'name' => 'Test Edition',
                ],
            ]);
    }

    public function test_manager_can_view_edition_divisions_with_pagination()
    {
        Sanctum::actingAs($this->manager);

        // Create multiple edition divisions
        EditionDivision::factory()->count(25)->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/divisions?page=1&per_page=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_edition_division',
                        'edition_id',
                        'division_id',
                        'players_limit',
                        'court',
                        'playing_start_time',
                        'playing_end_time',
                        'player_opponents_nr',
                        'player_matches_repeat',
                        'include_scores',
                        'has_demonstrative_match',
                        'fixed_teams',
                        'inserted_at',
                        'updated_at',
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
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    public function test_manager_can_view_edition_divisions_with_search()
    {
        Sanctum::actingAs($this->manager);

        // Create edition divisions with different court names
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'court' => 'Court A',
        ]);

        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'court' => 'Court B',
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/divisions?q=Court A");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Court A', $response->json('data.0.court'));
    }

    public function test_manager_can_view_edition_divisions_with_filters()
    {
        Sanctum::actingAs($this->manager);

        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'include_scores' => true,
        ]);

        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
            'include_scores' => false,
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/divisions?filters[players_limit]=16");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(16, $response->json('data.0.players_limit'));
    }

    public function test_manager_can_view_edition_divisions_with_sorting()
    {
        Sanctum::actingAs($this->manager);

        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
        ]);

        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 32,
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/divisions?sort_by[players_limit]=desc");

        $response->assertStatus(200);
        $this->assertEquals(32, $response->json('data.0.players_limit'));
        $this->assertEquals(16, $response->json('data.1.players_limit'));
    }

    public function test_manager_can_view_edition_participants_with_pagination()
    {
        Sanctum::actingAs($this->manager);

        // Create multiple participants
        $players = Player::factory()->count(25)->create();
        foreach ($players as $player) {
            EditionParticipation::factory()->create([
                'edition_id' => $this->edition->id_edition,
                'player_id' => $player->id_player,
                'division_id' => $this->division->id_division,
            ]);
        }

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participants?page=1&per_page=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_participation',
                        'edition_id',
                        'player_id',
                        'division_id',
                        'is_minor',
                        'is_student',
                        'discount',
                        'payment',
                        'absence_reason',
                        'ban_reason',
                        'inserted_at',
                        'updated_at',
                        'player' => [
                            'id_player',
                            'name',
                            'phone',
                        ],
                        'division' => [
                            'id_division',
                            'name',
                            'color',
                        ],
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
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    public function test_manager_can_view_edition_participants_with_search()
    {
        Sanctum::actingAs($this->manager);

        $player1 = Player::factory()->create(['name' => 'John Doe']);
        $player2 = Player::factory()->create(['name' => 'Jane Smith']);

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player1->id_player,
            'division_id' => $this->division->id_division,
            'absence_reason' => 'SICK',
        ]);

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player2->id_player,
            'division_id' => $this->division->id_division,
            'absence_reason' => 'EXAMS',
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participants?q=SICK");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('SICK', $response->json('data.0.absence_reason'));
    }

    public function test_manager_can_view_edition_participants_with_filters()
    {
        Sanctum::actingAs($this->manager);

        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player1->id_player,
            'division_id' => $this->division->id_division,
            'is_student' => true,
            'payment' => 25.00,
        ]);

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player2->id_player,
            'division_id' => $this->division->id_division,
            'is_student' => false,
            'payment' => 50.00,
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participants?filters[is_student]=1");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue((bool) $response->json('data.0.is_student'));
    }

    public function test_manager_can_view_edition_lotteries_with_pagination()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();

        // Create multiple lotteries
        EditionLottery::factory()->count(25)->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'prize_id' => $prize->id_prize,
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/lotteries?page=1&per_page=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_lottery',
                        'edition_id',
                        'division_id',
                        'prize_id',
                        'orderliness',
                        'total_draws_nr',
                        'inserted_at',
                        'updated_at',
                        'prize' => [
                            'id_prize',
                            'name',
                        ],
                        'division' => [
                            'id_division',
                            'name',
                            'color',
                        ],
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
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    public function test_manager_can_view_edition_lotteries_with_filters()
    {
        Sanctum::actingAs($this->manager);

        $prize1 = Prize::factory()->create();
        $prize2 = Prize::factory()->create();

        EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'prize_id' => $prize1->id_prize,
            'orderliness' => 1,
        ]);

        EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'prize_id' => $prize2->id_prize,
            'orderliness' => 2,
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/lotteries?filters[orderliness]=1");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('data.0.orderliness'));
    }

    public function test_manager_can_view_edition_lotteries_with_sorting()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();

        EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'prize_id' => $prize->id_prize,
            'orderliness' => 1,
        ]);

        EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'prize_id' => $prize->id_prize,
            'orderliness' => 2,
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/lotteries?sort_by[orderliness]=desc");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.0.orderliness'));
        $this->assertEquals(1, $response->json('data.1.orderliness'));
    }

    public function test_edition_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/editions');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_edition_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/editions');

        $response->assertStatus(403);
    }

    public function test_edition_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/editions/99999');

        $response->assertStatus(404);
    }

    public function test_edition_divisions_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/editions/99999/divisions');

        $response->assertStatus(404);
    }

    public function test_edition_participants_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/editions/99999/participants');

        $response->assertStatus(404);
    }

    public function test_edition_lotteries_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/editions/99999/lotteries');

        $response->assertStatus(404);
    }
}

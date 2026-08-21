<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionPoster;
use App\Models\Location;
use App\Models\Organizer;
use App\Models\Player;
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
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
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
        ]);
    }

    public function test_manager_can_view_editions()
    {
        Sanctum::actingAs($this->manager);

        // Clear editions without violating divisions.its_first_edition_id FK
        Division::query()->delete();
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

    public function test_manager_can_update_edition()
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::factory()->create([
            'location_id' => $this->location->id_location,
            'name' => 'Original Edition',
            'date' => now()->addDays(30),
        ]);

        $updateData = [
            'name' => 'Updated Edition',
            'date' => now()->addDays(60)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
        ];

        $response = $this->putJson("/api/v1/manager/editions/{$edition->id_edition}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition updated successfully',
                'data' => [
                    'id_edition' => $edition->id_edition,
                    'name' => 'Updated Edition',
                ],
            ]);
    }

    public function test_manager_can_partially_update_edition()
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::factory()->create([
            'location_id' => $this->location->id_location,
            'name' => 'Original Edition',
            'date' => now()->addDays(30),
        ]);

        // Test partial update with only name
        $response = $this->patchJson("/api/v1/manager/editions/{$edition->id_edition}", [
            'name' => 'Partially Updated Edition',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Edition',
                ],
            ]);

        // Test partial update with only date
        $response = $this->patchJson("/api/v1/manager/editions/{$edition->id_edition}", [
            'date' => now()->addDays(60)->format('Y-m-d'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Edition', // Should remain from previous update
                ],
            ]);
    }

    public function test_manager_cannot_update_edition_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::factory()->create([
            'location_id' => $this->location->id_location,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/editions/{$edition->id_edition}", [
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/editions/{$edition->id_edition}", [
            'name' => '',
            'date' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'date']);
    }

    public function test_edition_creation_defaults_branding(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/editions', [
            'name' => 'Branding Defaults',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.organizer_id', null)
            ->assertJsonPath('data.include_club_host_on_pdf', true);

        $this->assertDatabaseHas('editions', [
            'id_edition' => $response->json('data.id_edition'),
            'organizer_id' => null,
            'include_club_host_on_pdf' => 1,
        ]);
    }

    public function test_manager_can_create_edition_with_branding(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create(['name' => 'Branding Tour']);

        $response = $this->postJson('/api/v1/manager/editions', [
            'name' => 'Branded Edition',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.organizer_id', $organizer->id_organizer)
            ->assertJsonPath('data.include_club_host_on_pdf', false)
            ->assertJsonPath('data.organizer.name', 'Branding Tour');
    }

    public function test_edition_branding_rejects_unknown_organizer(): void
    {
        Sanctum::actingAs($this->manager);

        $this->patchJson("/api/v1/manager/editions/{$this->edition->id_edition}", [
            'organizer_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['organizer_id']);
    }

    public function test_manager_can_patch_edition_branding(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();

        $this->patchJson("/api/v1/manager/editions/{$this->edition->id_edition}", [
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.organizer_id', $organizer->id_organizer)
            ->assertJsonPath('data.include_club_host_on_pdf', false)
            ->assertJsonPath('data.organizer.id_organizer', $organizer->id_organizer);

        $this->patchJson("/api/v1/manager/editions/{$this->edition->id_edition}", [
            'organizer_id' => null,
        ])->assertStatus(200)
            ->assertJsonPath('data.organizer_id', null);

        $this->assertDatabaseHas('editions', [
            'id_edition' => $this->edition->id_edition,
            'organizer_id' => null,
            'include_club_host_on_pdf' => 0,
        ]);
    }

    public function test_edition_branding_is_patchable_after_finalize(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();
        $edition = Edition::factory()->create([
            'location_id' => $this->location->id_location,
            'finalized_at' => time(),
        ]);

        $this->patchJson("/api/v1/manager/editions/{$edition->id_edition}", [
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.organizer_id', $organizer->id_organizer)
            ->assertJsonPath('data.include_club_host_on_pdf', false);
    }

    public function test_edition_branding_change_keeps_generated_posters(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();
        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
        ]);

        $poster = new EditionDivisionPoster();
        $poster->edition_division_id = $editionDivision->id_edition_division;
        $poster->poster_kind = 'RANKING';
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'edition-division-posters/' . $poster->id_edition_division_poster . '/generated/ranking.pdf';
        $poster->save();

        $this->patchJson("/api/v1/manager/editions/{$this->edition->id_edition}", [
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
        ])->assertStatus(200);

        $this->assertNotNull($poster->fresh()->generated_path);
    }

    public function test_manager_can_create_edition_with_overnight_and_end_of_day_closing(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/editions', [
            'name' => 'Night Edition',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '18:00:00',
            'closing_time' => '24:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.arriving_time', '18:00:00')
            ->assertJsonPath('data.closing_time', '24:00:00');

        $overnight = $this->postJson('/api/v1/manager/editions', [
            'name' => 'Overnight Ed',
            'date' => now()->addDays(11)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '22:00:00',
            'closing_time' => '02:00:00',
        ]);

        $overnight->assertStatus(201)
            ->assertJsonPath('data.closing_time', '02:00:00');
    }
}

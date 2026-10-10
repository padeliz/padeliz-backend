<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionPoster;
use App\Models\Location;
use App\Models\Organizer;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Services\EventDeadlineTimestamps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $club;

    private $location;

    private $event;

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

        $this->event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'name' => 'Test PadelEvent',
            'date' => now()->addDays(30),
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
            'color' => '#FF0000',
        ]);
    }

    private function deadlineFieldsForDate(string $dateYmd): array
    {
        $deadline = EventDeadlineTimestamps::defaultDeadline($dateYmd);

        return [
            'registration_allowed_until' => $deadline,
            'cancellation_allowed_until' => $deadline,
        ];
    }

    public function test_manager_can_view_events()
    {
        Sanctum::actingAs($this->manager);

        // Clear editions without violating divisions.its_first_event_id FK
        Division::query()->delete();
        PadelEvent::query()->delete();
        PadelEvent::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_event',
                        'location_id',
                        'name',
                        'date',
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

        $response = $this->getJson("/api/v1/manager/events/{$this->event->id_event}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_event',
                    'location_id',
                    'name',
                    'date',
                    'arriving_time',
                    'closing_time',
                    'inserted_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent details retrieved successfully',
                'data' => [
                    'id_event' => $this->event->id_event,
                    'name' => 'Test PadelEvent',
                ],
            ]);
    }

    public function test_edition_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/events');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_edition_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/events');

        $response->assertStatus(403);
    }

    public function test_edition_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/events/99999');

        $response->assertStatus(404);
    }

    public function test_event_divisions_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/events/99999/divisions');

        $response->assertStatus(404);
    }

    public function test_manager_can_update_edition()
    {
        Sanctum::actingAs($this->manager);

        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'name' => 'Original PadelEvent',
            'date' => now()->addDays(30),
        ]);

        $updateData = [
            'name' => 'Updated PadelEvent',
            'description' => '<p>Updated description.</p>',
            'date' => now()->addDays(60)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(60)->format('Y-m-d')),
        ];

        $response = $this->putJson("/api/v1/manager/events/{$event->id_event}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent updated successfully',
                'data' => [
                    'id_event' => $event->id_event,
                    'name' => 'Updated PadelEvent',
                ],
            ]);
    }

    public function test_manager_can_partially_update_edition()
    {
        Sanctum::actingAs($this->manager);

        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'name' => 'Original PadelEvent',
            'date' => now()->addDays(30),
        ]);

        // Test partial update with only name
        $response = $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Partial Update',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent partially updated successfully',
                'data' => [
                    'name' => 'Partial Update',
                ],
            ]);

        // Test partial update with only date
        $response = $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'date' => now()->addDays(60)->format('Y-m-d'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent partially updated successfully',
                'data' => [
                    'name' => 'Partial Update', // Should remain from previous update
                ],
            ]);
    }

    public function test_manager_cannot_update_edition_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/events/{$event->id_event}", [
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => '',
            'description' => '',
            'date' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'date']);
    }

    public function test_edition_creation_defaults_branding(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Branding Defaults',
            'description' => '<p>Branding defaults description.</p>',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(5)->format('Y-m-d')),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.organizer_id', null)
            ->assertJsonPath('data.include_club_host_on_pdf', true);

        $this->assertDatabaseHas('events', [
            'id_event' => $response->json('data.id_event'),
            'organizer_id' => null,
            'include_club_host_on_pdf' => 1,
        ]);
    }

    public function test_manager_can_create_edition_with_branding(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create(['name' => 'Branding Tour']);

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Branded PadelEvent',
            'description' => '<p>Branded edition description.</p>',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(5)->format('Y-m-d')),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.organizer_id', $organizer->id_organizer)
            ->assertJsonPath('data.include_club_host_on_pdf', false)
            ->assertJsonPath('data.organizer.name', 'Branding Tour');
    }

    public function test_edition_branding_rejects_unknown_organizer(): void
    {
        Sanctum::actingAs($this->manager);

        $this->patchJson("/api/v1/manager/events/{$this->event->id_event}", [
            'organizer_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['organizer_id']);
    }

    public function test_manager_can_patch_edition_branding(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();

        $this->patchJson("/api/v1/manager/events/{$this->event->id_event}", [
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.organizer_id', $organizer->id_organizer)
            ->assertJsonPath('data.include_club_host_on_pdf', false)
            ->assertJsonPath('data.organizer.id_organizer', $organizer->id_organizer);

        $this->patchJson("/api/v1/manager/events/{$this->event->id_event}", [
            'organizer_id' => null,
        ])->assertStatus(200)
            ->assertJsonPath('data.organizer_id', null);

        $this->assertDatabaseHas('events', [
            'id_event' => $this->event->id_event,
            'organizer_id' => null,
            'include_club_host_on_pdf' => 0,
        ]);
    }

    public function test_edition_branding_is_patchable_after_finalize(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();
        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'finalized_at' => time(),
        ]);

        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
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
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
        ]);

        $poster = new EventDivisionPoster();
        $poster->event_division_id = $eventDivision->id_event_division;
        $poster->poster_kind = 'RANKING';
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'event-division-posters/' . $poster->id_event_division_poster . '/generated/ranking.pdf';
        $poster->save();

        $this->patchJson("/api/v1/manager/events/{$this->event->id_event}", [
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
        ])->assertStatus(200);

        $this->assertNotNull($poster->fresh()->generated_path);
    }

    public function test_manager_can_create_edition_with_overnight_closing(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Night PadelEvent',
            'description' => '<p>Night edition description.</p>',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '18:00:00',
            'closing_time' => '00:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(10)->format('Y-m-d')),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.arriving_time', '18:00:00')
            ->assertJsonPath('data.closing_time', '00:00:00');

        $overnight = $this->postJson('/api/v1/manager/events', [
            'name' => 'Overnight Ed',
            'description' => '<p>Overnight edition description.</p>',
            'date' => now()->addDays(11)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '22:00:00',
            'closing_time' => '02:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(11)->format('Y-m-d')),
        ]);

        $overnight->assertStatus(201)
            ->assertJsonPath('data.closing_time', '02:00:00');

        $fullDay = $this->postJson('/api/v1/manager/events', [
            'name' => 'Full Day Ed',
            'description' => '<p>Full day edition description.</p>',
            'date' => now()->addDays(12)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '00:00:00',
            'closing_time' => '23:59:59',
            ...$this->deadlineFieldsForDate(now()->addDays(12)->format('Y-m-d')),
        ]);

        $fullDay->assertStatus(201)
            ->assertJsonPath('data.closing_time', '23:59:59');
    }

    public function test_manager_cannot_create_edition_with_24_00_or_equal_clocks(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Bad End',
            'description' => '<p>Bad end.</p>',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '18:00:00',
            'closing_time' => '24:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(10)->format('Y-m-d')),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['closing_time']);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Equal Clocks',
            'description' => '<p>Equal clocks.</p>',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '12:00:00',
            'closing_time' => '12:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(10)->format('Y-m-d')),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['closing_time']);
    }

    public function test_store_requires_non_empty_description_and_sanitizes_html(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'No Desc',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(5)->format('Y-m-d')),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Empty Desc',
            'description' => '<p></p><script>alert(1)</script>',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(5)->format('Y-m-d')),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Rich Desc',
            'description' => '<p>Hello <strong>world</strong><script>alert(1)</script></p><a href="https://padeliz.ro" onclick="x()">link</a><img src="x">',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(5)->format('Y-m-d')),
        ]);

        $response->assertStatus(201);
        $description = $response->json('data.description');
        $this->assertStringContainsString('<p>', $description);
        $this->assertStringContainsString('<strong>world</strong>', $description);
        $this->assertStringContainsString('href="https://padeliz.ro"', $description);
        $this->assertStringNotContainsString('<script>', $description);
        $this->assertStringNotContainsString('onclick', $description);
        $this->assertStringNotContainsString('<img', $description);
        $this->assertNull($response->json('data.cover_url'));
    }

    public function test_update_and_patch_require_cover_path(): void
    {
        Sanctum::actingAs($this->manager);

        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'cover_path' => null,
        ]);

        $this->putJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Needs Cover',
            'description' => '<p>Needs cover.</p>',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(10)->format('Y-m-d')),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cover_path']);

        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Still Needs Cover',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cover_path']);

        $event->update(['cover_path' => 'events/' . $event->id_event . '/cover/test.png']);

        $this->putJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Has Cover',
            'description' => '<p>Has cover.</p>',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            ...$this->deadlineFieldsForDate(now()->addDays(10)->format('Y-m-d')),
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Has Cover');

        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Patched With Cover',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Patched With Cover');
    }
}

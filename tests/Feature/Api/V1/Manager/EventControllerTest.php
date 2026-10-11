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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\EventUnixTestHelper;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;
use Carbon\Carbon;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;
    use EventUnixTestHelper;

    private $manager;

    private $club;

    private $location;

    private $event;

    private $division;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
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
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
        ]);
        $this->attachCover($this->event);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
            'color' => '#FF0000',
        ]);
    }

    public function test_manager_can_view_events()
    {
        Sanctum::actingAs($this->manager);

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
                        'event_starts_at',
                        'event_ends_at',
                        'event_nr_of_days',
                        'cover_images',
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
                    'event_starts_at',
                    'event_ends_at',
                    'cover_images',
                    'event_nr_of_days',
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
        $player = User::factory()->playerOnly()->create();
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
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
        ]);
        $this->attachCover($event);

        $ymd = now()->addDays(60)->format('Y-m-d');
        $window = $this->eventWindow($ymd);
        $updateData = [
            'name' => 'Updated PadelEvent',
            'description' => '<p>Updated description.</p>',
            'location_id' => $this->location->id_location,
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
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
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
        ]);
        $this->attachCover($event);

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

        $ymd = now()->addDays(60)->format('Y-m-d');
        $window = $this->eventWindow($ymd);
        $response = $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PadelEvent partially updated successfully',
                'data' => [
                    'name' => 'Partial Update',
                ],
            ]);
    }

    public function test_manager_cannot_update_edition_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
        ]);
        $this->attachCover($event);

        $response = $this->putJson("/api/v1/manager/events/{$event->id_event}", [
            'event_starts_at' => $event->event_starts_at,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'event_ends_at']);

        $response = $this->putJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => '',
            'description' => '',
            'event_starts_at' => '',
            'event_ends_at' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'event_starts_at', 'event_ends_at']);
    }

    public function test_edition_creation_defaults_branding(): void
    {
        Sanctum::actingAs($this->manager);

        $ymd = now()->addDays(5)->format('Y-m-d');
        $window = $this->eventWindow($ymd);

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Branding Defaults',
            'description' => '<p>Branding defaults description.</p>',
            'location_id' => $this->location->id_location,
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
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
        $ymd = now()->addDays(5)->format('Y-m-d');
        $window = $this->eventWindow($ymd);

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Branded PadelEvent',
            'description' => '<p>Branded edition description.</p>',
            'location_id' => $this->location->id_location,
            'organizer_id' => $organizer->id_organizer,
            'include_club_host_on_pdf' => false,
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
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
        $this->attachCover($event);

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

        $ymd = now()->addDays(10)->format('Y-m-d');
        $window = $this->eventWindow($ymd, '18:00:00', '00:00:00');

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Night PadelEvent',
            'description' => '<p>Night edition description.</p>',
            'location_id' => $this->location->id_location,
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.event_starts_at', $window['event_starts_at'])
            ->assertJsonPath('data.event_ends_at', $window['event_ends_at'])
            ->assertJsonPath('data.event_nr_of_days', 2);

        $overnightYmd = now()->addDays(11)->format('Y-m-d');
        $overnightWindow = $this->eventWindow($overnightYmd, '22:00:00', '02:00:00');
        $overnight = $this->postJson('/api/v1/manager/events', [
            'name' => 'Overnight Ed',
            'description' => '<p>Overnight edition description.</p>',
            'location_id' => $this->location->id_location,
            ...$overnightWindow,
            ...$this->deadlineFieldsForStartsAt($overnightWindow['event_starts_at']),
        ]);

        $overnight->assertStatus(201)
            ->assertJsonPath('data.event_ends_at', $overnightWindow['event_ends_at'])
            ->assertJsonPath('data.event_nr_of_days', 2);

        $fullDayYmd = now()->addDays(12)->format('Y-m-d');
        $fullDayWindow = $this->eventWindow($fullDayYmd, '00:00:00', '23:59:59');
        $fullDay = $this->postJson('/api/v1/manager/events', [
            'name' => 'Full Day Ed',
            'description' => '<p>Full day edition description.</p>',
            'location_id' => $this->location->id_location,
            ...$fullDayWindow,
            ...$this->deadlineFieldsForStartsAt($fullDayWindow['event_starts_at']),
        ]);

        $fullDay->assertStatus(201)
            ->assertJsonPath('data.event_ends_at', $fullDayWindow['event_ends_at'])
            ->assertJsonPath('data.event_nr_of_days', 1);
    }

    public function test_manager_cannot_create_edition_with_equal_or_inverted_bounds(): void
    {
        Sanctum::actingAs($this->manager);

        $ymd = now()->addDays(10)->format('Y-m-d');
        $startsAt = (int) Carbon::parse($ymd . ' 18:00:00', config('app.timezone'))->timestamp;

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Bad End',
            'description' => '<p>Bad end.</p>',
            'location_id' => $this->location->id_location,
            'event_starts_at' => $startsAt,
            'event_ends_at' => $startsAt,
            ...$this->deadlineFieldsForStartsAt($startsAt),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_ends_at']);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Equal Clocks',
            'description' => '<p>Equal clocks.</p>',
            'location_id' => $this->location->id_location,
            'event_starts_at' => $startsAt,
            'event_ends_at' => $startsAt - 60,
            ...$this->deadlineFieldsForStartsAt($startsAt),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_ends_at']);
    }

    public function test_store_requires_non_empty_description_and_sanitizes_html(): void
    {
        Sanctum::actingAs($this->manager);

        $ymd = now()->addDays(5)->format('Y-m-d');
        $window = $this->eventWindow($ymd);
        $deadlines = $this->deadlineFieldsForStartsAt($window['event_starts_at']);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'No Desc',
            'location_id' => $this->location->id_location,
            ...$window,
            ...$deadlines,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Empty Desc',
            'description' => '<p></p><script>alert(1)</script>',
            'location_id' => $this->location->id_location,
            ...$window,
            ...$deadlines,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        $response = $this->postJson('/api/v1/manager/events', [
            'name' => 'Rich Desc',
            'description' => '<p>Hello <strong>world</strong><script>alert(1)</script></p><a href="https://padeliz.ro" onclick="x()">link</a><img src="x">',
            'location_id' => $this->location->id_location,
            ...$window,
            ...$deadlines,
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
        $this->assertSame([], $response->json('data.cover_images'));
    }

    public function test_update_and_patch_require_cover_images(): void
    {
        Sanctum::actingAs($this->manager);

        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
        ]);

        $ymd = now()->addDays(10)->format('Y-m-d');
        $window = $this->eventWindow($ymd);
        $payload = [
            'name' => 'Needs Cover',
            'description' => '<p>Needs cover.</p>',
            'location_id' => $this->location->id_location,
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
        ];

        $this->putJson("/api/v1/manager/events/{$event->id_event}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cover_images']);

        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Still Needs Cover',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cover_images']);

        $this->attachCover($event);

        $this->putJson("/api/v1/manager/events/{$event->id_event}", [
            ...$payload,
            'name' => 'Has Cover',
            'description' => '<p>Has cover.</p>',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Has Cover');

        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Patched With Cover',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Patched With Cover');
    }
}

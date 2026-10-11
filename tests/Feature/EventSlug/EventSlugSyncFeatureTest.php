<?php

declare(strict_types=1);

namespace Tests\Feature\EventSlug;

use App\Models\Location;
use App\Models\Organizer;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\EventSlug\EventSlugSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EventSlugSyncFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('filesystems.user_uploads_disk', 'public'));
    }

    public function test_manager_create_sets_non_null_slug(): void
    {
        $this->actingAsManager();
        $location = Location::factory()->create();
        $organizer = Organizer::factory()->create(['name' => 'Padel Club']);

        $response = $this->postJson('/api/v1/manager/events', $this->validPayload($location->id_location, $organizer->id_organizer, 'Summer Open'));

        $response->assertCreated();
        $id = (int) $response->json('data.id_event');
        $event = PadelEvent::query()->findOrFail($id);
        $this->assertSame('padel-club-summer-open', $event->slug);
        $this->assertSame('padel-club-summer-open', $response->json('data.slug'));
    }

    public function test_manager_rejects_name_that_does_not_slugify(): void
    {
        $this->actingAsManager();
        $location = Location::factory()->create();

        $response = $this->postJson('/api/v1/manager/events', $this->validPayload($location->id_location, null, '!!!'));

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_inbound_slug_is_ignored(): void
    {
        $this->actingAsManager();
        $location = Location::factory()->create();

        $payload = $this->validPayload($location->id_location, null, 'Summer Open');
        $payload['slug'] = 'client-forged';

        $response = $this->postJson('/api/v1/manager/events', $payload);
        $response->assertCreated();
        $this->assertSame('summer-open', $response->json('data.slug'));
        $this->assertNotSame('client-forged', PadelEvent::query()->findOrFail((int) $response->json('data.id_event'))->slug);
    }

    public function test_dirty_name_recomputs_slug_unrelated_patch_does_not(): void
    {
        $this->actingAsManager();
        $location = Location::factory()->create();
        $event = PadelEvent::factory()->create([
            'location_id' => $location->id_location,
            'organizer_id' => null,
            'name' => 'Alpha',
            'slug' => 'alpha',
        ]);
        \Tests\Support\UnixEventFactory::withCover($event);

        $this->patchJson('/api/v1/manager/events/' . $event->id_event, [
            'name' => 'Beta Cup',
        ])->assertOk();
        $this->assertSame('beta-cup', $event->fresh()->slug);

        $before = $event->fresh()->slug;
        $this->patchJson('/api/v1/manager/events/' . $event->id_event, [
            'include_club_host_on_pdf' => false,
        ])->assertOk();
        $this->assertSame($before, $event->fresh()->slug);
    }

    public function test_organizer_rename_updates_linked_event_slugs(): void
    {
        $this->actingAsManager();
        $location = Location::factory()->create();
        $organizer = Organizer::factory()->create(['name' => 'Old Club']);
        $event = PadelEvent::factory()->create([
            'location_id' => $location->id_location,
            'organizer_id' => $organizer->id_organizer,
            'name' => 'Open',
            'slug' => 'old-club-open',
        ]);

        $this->putJson('/api/v1/manager/organizers/' . $organizer->id_organizer, [
            'name' => 'New Club',
        ])->assertOk();

        $this->assertSame('new-club-open', $event->fresh()->slug);
    }

    public function test_organizer_rename_rolls_back_when_sync_throws(): void
    {
        $this->actingAsManager();
        $organizer = Organizer::factory()->create(['name' => 'Stable Club']);
        $location = Location::factory()->create();
        PadelEvent::factory()->create([
            'location_id' => $location->id_location,
            'organizer_id' => $organizer->id_organizer,
            'name' => 'Open',
            'slug' => 'stable-club-open',
        ]);

        $this->mock(EventSlugSync::class, function ($mock): void {
            $mock->shouldReceive('syncForOrganizer')->once()->andThrow(new \RuntimeException('boom'));
        });

        $this->putJson('/api/v1/manager/organizers/' . $organizer->id_organizer, [
            'name' => 'Broken Club',
        ])->assertStatus(500);

        $this->assertSame('Stable Club', $organizer->fresh()->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(int $locationId, ?int $organizerId, string $name): array
    {
        $bounds = \Tests\Support\UnixEventFactory::eventBounds(
            date('Y-m-d', time() + 86400 * 10)
        );

        return [
            'name' => $name,
            'description' => '<p>Hello</p>',
            'location_id' => $locationId,
            'organizer_id' => $organizerId,
            'event_starts_at' => $bounds['event_starts_at'],
            'event_ends_at' => $bounds['event_ends_at'],
            'registration_allowed_until' => time() + 86400 * 7,
            'cancellation_allowed_until' => time() + 86400 * 5,
        ];
    }

    private function actingAsManager(): void
    {
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);
    }
}

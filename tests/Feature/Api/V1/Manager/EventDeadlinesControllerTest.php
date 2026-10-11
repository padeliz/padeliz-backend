<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\EventDeadlineTimestamps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\EventUnixTestHelper;
use Tests\TestCase;

class EventDeadlinesControllerTest extends TestCase
{
    use RefreshDatabase;
    use EventUnixTestHelper;

    private User $manager;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $club = Club::factory()->create();
        $this->location = Location::factory()->create(['club_id' => $club->id_club]);
    }

    public function test_deadline_columns_are_not_null(): void
    {
        $this->assertTrue(Schema::hasColumn('events', 'registration_allowed_until'));
        $this->assertTrue(Schema::hasColumn('events', 'cancellation_allowed_until'));
        $this->assertFalse(Schema::hasColumn('events', 'registration_cancel_until'));

        $editionsTable = DB::getTablePrefix() . 'events';
        foreach (['registration_allowed_until', 'cancellation_allowed_until'] as $column) {
            $meta = DB::selectOne("SHOW COLUMNS FROM `{$editionsTable}` WHERE Field = ?", [$column]);
            $this->assertNotNull($meta);
            $this->assertSame('NO', $meta->Null);
        }
    }

    public function test_factory_sets_both_deadlines_with_valid_ordering(): void
    {
        $window = $this->eventWindow('2026-12-01');
        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
        ]);

        $this->assertLessThan($event->event_starts_at, $event->registration_allowed_until);
        $this->assertLessThanOrEqual($event->registration_allowed_until, $event->cancellation_allowed_until);
    }

    public function test_store_requires_both_deadlines(): void
    {
        Sanctum::actingAs($this->manager);
        $window = $this->eventWindow($this->futureEventYmd(10));

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Needs Deadlines',
            'description' => '<p>Desc</p>',
            'location_id' => $this->location->id_location,
            ...$window,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['registration_allowed_until', 'cancellation_allowed_until']);
    }

    public function test_store_rejects_cancellation_after_registration(): void
    {
        Sanctum::actingAs($this->manager);
        $window = $this->eventWindow($this->futureEventYmd(10));

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Bad Order',
            'description' => '<p>Desc</p>',
            'location_id' => $this->location->id_location,
            ...$window,
            'registration_allowed_until' => $window['event_starts_at'] - 10_000,
            'cancellation_allowed_until' => $window['event_starts_at'] - 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cancellation_allowed_until']);
    }

    public function test_store_rejects_registration_at_or_after_event_starts_at(): void
    {
        Sanctum::actingAs($this->manager);
        $window = $this->eventWindow($this->futureEventYmd(10));

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Too Late',
            'description' => '<p>Desc</p>',
            'location_id' => $this->location->id_location,
            ...$window,
            'registration_allowed_until' => $window['event_starts_at'],
            'cancellation_allowed_until' => $window['event_starts_at'] - 60,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['registration_allowed_until']);
    }

    public function test_patch_omit_deadlines_ok_and_start_move_validates(): void
    {
        Sanctum::actingAs($this->manager);
        $window = $this->eventWindow($this->futureEventYmd(20));
        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            ...$window,
            ...$this->deadlineFieldsForStartsAt($window['event_starts_at']),
        ]);
        $this->attachCover($event);

        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Renamed',
        ])->assertStatus(200);

        $earlyWindow = $this->eventWindow($this->futureEventYmd(1));
        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            ...$earlyWindow,
        ])->assertStatus(422);
    }

    public function test_resource_exposes_both_deadline_fields(): void
    {
        Sanctum::actingAs($this->manager);
        $window = $this->eventWindow($this->futureEventYmd(12));
        $deadline = EventDeadlineTimestamps::defaultDeadline($window['event_starts_at']);
        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            ...$window,
            'registration_allowed_until' => $deadline,
            'cancellation_allowed_until' => $deadline - 100,
        ]);

        $this->getJson("/api/v1/manager/events/{$event->id_event}")
            ->assertStatus(200)
            ->assertJsonPath('data.registration_allowed_until', $deadline)
            ->assertJsonPath('data.cancellation_allowed_until', $deadline - 100);
    }
}

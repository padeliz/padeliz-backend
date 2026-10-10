<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Services\EventDeadlineTimestamps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventDeadlinesControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
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
        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'date' => '2026-12-01',
            'registration_allowed_until' => EventDeadlineTimestamps::defaultDeadline('2026-12-01'),
            'cancellation_allowed_until' => EventDeadlineTimestamps::defaultDeadline('2026-12-01'),
        ]);

        $start = EventDeadlineTimestamps::startOfEditionDay('2026-12-01');
        $this->assertLessThanOrEqual($start, $event->registration_allowed_until);
        $this->assertLessThanOrEqual($event->registration_allowed_until, $event->cancellation_allowed_until);
    }

    public function test_store_requires_both_deadlines(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Needs Deadlines',
            'description' => '<p>Desc</p>',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['registration_allowed_until', 'cancellation_allowed_until']);
    }

    public function test_store_rejects_cancellation_after_registration(): void
    {
        Sanctum::actingAs($this->manager);
        $date = now()->addDays(10)->format('Y-m-d');
        $start = EventDeadlineTimestamps::startOfEditionDay($date);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Bad Order',
            'description' => '<p>Desc</p>',
            'date' => $date,
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            'registration_allowed_until' => $start - 10_000,
            'cancellation_allowed_until' => $start - 1000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cancellation_allowed_until']);
    }

    public function test_store_rejects_registration_after_start_of_edition_day(): void
    {
        Sanctum::actingAs($this->manager);
        $date = now()->addDays(10)->format('Y-m-d');
        $start = EventDeadlineTimestamps::startOfEditionDay($date);

        $this->postJson('/api/v1/manager/events', [
            'name' => 'Too Late',
            'description' => '<p>Desc</p>',
            'date' => $date,
            'location_id' => $this->location->id_location,
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
            'registration_allowed_until' => $start + 60,
            'cancellation_allowed_until' => $start - 60,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['registration_allowed_until']);
    }

    public function test_patch_omit_deadlines_ok_and_date_move_validates(): void
    {
        Sanctum::actingAs($this->manager);
        $date = now()->addDays(20)->format('Y-m-d');
        $deadline = EventDeadlineTimestamps::defaultDeadline($date);
        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'date' => $date,
            'registration_allowed_until' => $deadline,
            'cancellation_allowed_until' => $deadline,
        ]);

        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'name' => 'Renamed',
        ])->assertStatus(200);

        $earlyDate = now()->addDays(1)->format('Y-m-d');
        $this->patchJson("/api/v1/manager/events/{$event->id_event}", [
            'date' => $earlyDate,
        ])->assertStatus(422);
    }

    public function test_resource_exposes_both_deadline_fields(): void
    {
        Sanctum::actingAs($this->manager);
        $date = now()->addDays(12)->format('Y-m-d');
        $deadline = EventDeadlineTimestamps::defaultDeadline($date);
        $event = PadelEvent::factory()->create([
            'location_id' => $this->location->id_location,
            'date' => $date,
            'registration_allowed_until' => $deadline,
            'cancellation_allowed_until' => $deadline - 100,
        ]);

        $this->getJson("/api/v1/manager/events/{$event->id_event}")
            ->assertStatus(200)
            ->assertJsonPath('data.registration_allowed_until', $deadline)
            ->assertJsonPath('data.cancellation_allowed_until', $deadline - 100);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

final class EventRegistrationLevelGateTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    private PadelEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::factory()->playerOnly()->create();
        $this->event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
        ]);
        Sanctum::actingAs($this->player);
    }

    private function eventDivision(?string $levelMin, ?string $levelMax): EventDivision
    {
        $division = Division::factory()->create();

        return EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 10,
            'entry_fee' => 55,
            'level_min' => $levelMin,
            'level_max' => $levelMax,
        ]);
    }

    private function register(EventDivision $eventDivision): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $eventDivision->id_event_division,
        ]);
    }

    public function test_player_without_any_level_is_forbidden(): void
    {
        $this->register($this->eventDivision(null, null))->assertForbidden();
    }

    public function test_any_level_is_enough_when_bounds_are_open(): void
    {
        PlayerLevel::factory()->level('1.50')->create(['player_user_id' => $this->player->id_user]);

        $this->register($this->eventDivision(null, '4.00'))->assertCreated();
    }

    public function test_level_inside_bounds_can_register(): void
    {
        PlayerLevel::factory()->level('4.50')->create(['player_user_id' => $this->player->id_user]);

        $this->register($this->eventDivision('4.99', '4.00'))->assertCreated();
    }

    public function test_level_outside_bounds_is_forbidden(): void
    {
        PlayerLevel::factory()->level('3.50')->create(['player_user_id' => $this->player->id_user]);

        $this->register($this->eventDivision('4.99', '4.00'))->assertForbidden();
    }
}

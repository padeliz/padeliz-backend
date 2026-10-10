<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventParticipationReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorders_and_moves_participations_within_edition(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $edA = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionA->id_division,
        ]);
        $edB = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionB->id_division,
        ]);

        $p1 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edA->id_event_division,
            'sort_order' => 0,
        ]);
        $p2 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edA->id_event_division,
            'sort_order' => 1,
        ]);

        $response = $this->patchJson('/api/v1/manager/event-participations/reorder', [
            'participations' => [
                [
                    'id_participation' => $p1->id_participation,
                    'event_division_id' => $edA->id_event_division,
                    'sort_order' => 1,
                ],
                [
                    'id_participation' => $p2->id_participation,
                    'event_division_id' => $edB->id_event_division,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('event_participations', [
            'id_participation' => $p1->id_participation,
            'event_division_id' => $edA->id_event_division,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('event_participations', [
            'id_participation' => $p2->id_participation,
            'event_division_id' => $edB->id_event_division,
            'sort_order' => 0,
        ]);
    }

    public function test_rejects_when_edition_is_finalized(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create([
            'finalized_at' => time(),
        ]);
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $edA = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionA->id_division,
        ]);
        $edB = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionB->id_division,
        ]);

        $p1 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edA->id_event_division,
            'sort_order' => 0,
        ]);

        $this->patchJson('/api/v1/manager/event-participations/reorder', [
            'participations' => [
                [
                    'id_participation' => $p1->id_participation,
                    'event_division_id' => $edB->id_event_division,
                    'sort_order' => 0,
                ],
            ],
        ])->assertStatus(409);
    }
}

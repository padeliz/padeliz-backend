<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tz = (string) config('app.timezone', 'Europe/Bucharest');
    }

    public function test_guest_cannot_access_event_history(): void
    {
        $this->getJson('/api/v1/app/my/event-history')->assertUnauthorized();
    }

    public function test_aggregates_division_titles_per_edition(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $event = PadelEvent::factory()->create([
            'name' => '#History1',
            'date' => Carbon::now($this->tz)->subDays(5)->format('Y-m-d'),
        ]);
        $divisionA = Division::factory()->create(['name' => 'Open A']);
        $divisionB = Division::factory()->create(['name' => 'Open B']);
        $edA = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionA->id_division,
            'division_title' => 'Open A Title',
        ]);
        $edB = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionB->id_division,
            'division_title' => 'Open B Title',
        ]);

        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edA->id_event_division,
            'player_id' => $player->id_player,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edB->id_event_division,
            'player_id' => $player->id_player,
        ]);

        $response = $this->getJson('/api/v1/app/my/event-history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_event', $event->id_event)
            ->assertJsonPath('data.0.name', '#History1');

        $titles = $response->json('data.0.division_titles');
        $this->assertIsArray($titles);
        $this->assertCount(2, $titles);
        $this->assertContains('Open A Title', $titles);
        $this->assertContains('Open B Title', $titles);
    }

    public function test_orders_editions_by_date_descending(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $older = PadelEvent::factory()->create([
            'name' => '#Older',
            'date' => Carbon::now($this->tz)->subDays(20)->format('Y-m-d'),
        ]);
        $newer = PadelEvent::factory()->create([
            'name' => '#Newer',
            'date' => Carbon::now($this->tz)->subDays(3)->format('Y-m-d'),
        ]);

        foreach ([$older, $newer] as $event) {
            $division = EventDivision::factory()->create([
                'event_id' => $event->id_event,
                'division_title' => 'Div ' . $event->name,
            ]);
            EventParticipation::factory()->create([
                'event_id' => $event->id_event,
                'event_division_id' => $division->id_event_division,
                'player_id' => $player->id_player,
            ]);
        }

        $response = $this->getJson('/api/v1/app/my/event-history');

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame($newer->id_event, $response->json('data.0.id_event'));
        $this->assertSame($older->id_event, $response->json('data.1.id_event'));
        $this->assertSame(
            Carbon::now($this->tz)->subDays(3)->format('Y-m-d'),
            $response->json('data.0.date')
        );
    }

    public function test_excludes_other_players_participations(): void
    {
        $player = Player::factory()->playerOnly()->create();
        $other = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $mine = PadelEvent::factory()->create([
            'date' => Carbon::now($this->tz)->subDays(2)->format('Y-m-d'),
        ]);
        $theirs = PadelEvent::factory()->create([
            'date' => Carbon::now($this->tz)->subDays(1)->format('Y-m-d'),
        ]);

        $mineDivision = EventDivision::factory()->create(['event_id' => $mine->id_event]);
        $theirsDivision = EventDivision::factory()->create(['event_id' => $theirs->id_event]);

        EventParticipation::factory()->create([
            'event_id' => $mine->id_event,
            'event_division_id' => $mineDivision->id_event_division,
            'player_id' => $player->id_player,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $theirs->id_event,
            'event_division_id' => $theirsDivision->id_event_division,
            'player_id' => $other->id_player,
        ]);

        $this->getJson('/api/v1/app/my/event-history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_event', $mine->id_event);
    }

    public function test_includes_event_slug_for_detail_links(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $event = PadelEvent::factory()->create([
            'name' => '#Slugged',
            'date' => Carbon::now($this->tz)->subDays(4)->format('Y-m-d'),
        ]);
        $event->forceFill(['slug' => 'padeliz-slugged'])->saveQuietly();
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_id' => $player->id_player,
        ]);

        $this->getJson('/api/v1/app/my/event-history')
            ->assertOk()
            ->assertJsonPath('data.0.id_event', $event->id_event)
            ->assertJsonPath('data.0.slug', 'padeliz-slugged');
    }
}

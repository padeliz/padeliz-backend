<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionReplacement;
use App\Models\EventDivisionSchedule;
use App\Models\EventParticipation;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\PadelEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventDivisionReplacementControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    private EventDivisionSchedule $schedule;

    /** @var list<EventParticipation> */
    private array $participations = [];

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create();
        $division = Division::factory()->create();

        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
        ]);

        $this->schedule = EventDivisionSchedule::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'allow_replacements' => true,
        ]);

        $court = Court::factory()->create();
        $edCourt = EventDivisionCourt::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->participations[] = EventParticipation::factory()->create([
                'event_id' => $this->event->id_event,
                'event_division_id' => $this->eventDivision->id_event_division,
                'player_user_id' => User::factory()->create()->id_user,
                'sort_order' => $i,
                'is_collecting_points' => true,
            ]);
        }

        $this->match = GameMatch::create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'event_division_court_id' => $edCourt->id_event_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 1,
            'scheduled_start' => '14:00:00',
            'scheduled_end' => '14:30:00',
            'points_target' => 21,
        ]);

        $left = MatchSide::create(['match_id' => $this->match->id_match, 'side' => 'LEFT', 'score' => null]);
        $right = MatchSide::create(['match_id' => $this->match->id_match, 'side' => 'RIGHT', 'score' => null]);

        MatchPlayer::create([
            'match_side_id' => $left->id_match_side,
            'slot' => 0,
            'participation_id' => $this->participations[0]->id_participation,
            'player_user_id' => $this->participations[0]->player_user_id,
        ]);
        MatchPlayer::create([
            'match_side_id' => $left->id_match_side,
            'slot' => 1,
            'participation_id' => $this->participations[1]->id_participation,
            'player_user_id' => $this->participations[1]->player_user_id,
        ]);
        MatchPlayer::create([
            'match_side_id' => $right->id_match_side,
            'slot' => 0,
            'participation_id' => $this->participations[2]->id_participation,
            'player_user_id' => $this->participations[2]->player_user_id,
        ]);
        MatchPlayer::create([
            'match_side_id' => $right->id_match_side,
            'slot' => 1,
            'participation_id' => $this->participations[3]->id_participation,
            'player_user_id' => $this->participations[3]->player_user_id,
        ]);
    }

    public function test_create_list_and_delete_replacement(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $originalId = $this->participations[0]->id_participation;
        $substituteId = $this->participations[3]->id_participation;

        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $originalId,
            'substitute_participation_id' => $substituteId,
            'match_id' => $this->match->id_match,
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_participation_id', $originalId)
            ->assertJsonPath('data.substitute_participation_id', $substituteId)
            ->assertJsonPath('data.match_id', $this->match->id_match);

        $replacementId = (int) $create->json('data.id_replacement');
        $this->assertDatabaseHas('event_division_replacements', [
            'id_replacement' => $replacementId,
            'event_division_id' => $edId,
        ]);

        $slot = MatchPlayer::query()->where('participation_id', $originalId)->firstOrFail();
        $this->assertSame($originalId, (int) $slot->participation_id);
        $this->assertSame((int) $this->participations[0]->player_user_id, (int) $slot->player_user_id);
        $this->assertArrayNotHasKey('got_replaced', $slot->getAttributes());

        $this->getJson("/api/v1/manager/event-divisions/{$edId}/replacements")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/manager/event-division-replacements/{$replacementId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('event_division_replacements', [
            'id_replacement' => $replacementId,
        ]);
        $slotAfter = MatchPlayer::query()->where('participation_id', $originalId)->firstOrFail();
        $this->assertSame($originalId, (int) $slotAfter->participation_id);
        $this->assertSame((int) $this->participations[0]->player_user_id, (int) $slotAfter->player_user_id);
    }

    public function test_finalize_blocks_replacements(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($this->event->fresh()->finalized_at);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertStatus(409);

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[3]->id_participation,
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);

        $this->assertSame(0, EventDivisionReplacement::query()->count());
    }

    public function test_replacement_blocked_when_not_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $this->schedule->update(['allow_replacements' => false]);
        $edId = $this->eventDivision->id_event_division;

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_player_user_id' => User::factory()->create()->id_user,
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);
    }

    public function test_delete_replacement_blocked_after_finalize(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[3]->id_participation,
        ]);
        $create->assertStatus(201);
        $replacementId = (int) $create->json('data.id_replacement');

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk();

        $this->deleteJson("/api/v1/manager/event-division-replacements/{$replacementId}")
            ->assertStatus(409);

        $this->assertDatabaseHas('event_division_replacements', [
            'id_replacement' => $replacementId,
        ]);
    }

    public function test_index_for_edition_lists_all_division_replacements(): void
    {
        Sanctum::actingAs($this->manager);

        $otherDivision = Division::factory()->create();
        $otherEd = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $otherDivision->id_division,
            'division_title' => 'Other ED',
        ]);
        EventDivisionSchedule::factory()->create([
            'event_division_id' => $otherEd->id_event_division,
            'allow_replacements' => true,
        ]);

        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
        ])->assertStatus(201);

        $otherPartA = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $otherEd->id_event_division,
            'player_user_id' => User::factory()->create()->id_user,
            'sort_order' => 0,
        ]);
        $otherPartB = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $otherEd->id_event_division,
            'player_user_id' => User::factory()->create()->id_user,
            'sort_order' => 1,
        ]);
        $this->postJson("/api/v1/manager/event-divisions/{$otherEd->id_event_division}/replacements", [
            'original_participation_id' => $otherPartA->id_participation,
            'substitute_participation_id' => $otherPartB->id_participation,
        ])->assertStatus(201);

        $response = $this->getJson(
            "/api/v1/manager/events/{$this->event->id_event}/replacements"
        );
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertNotNull($data[0]['event_division']['id_event_division'] ?? null);
        $this->assertNotNull($data[0]['original_participation']['player']['id_user'] ?? null);
        $this->assertNotNull($data[0]['substitute_participation']['player']['id_user'] ?? null);
    }

    public function test_create_with_neither_substitute(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'match_id' => $this->match->id_match,
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.substitute_participation_id', null)
            ->assertJsonPath('data.substitute_player_user_id', null);
    }

    public function test_create_with_only_guest_substitute(): void
    {
        Sanctum::actingAs($this->manager);

        $guestId = User::factory()->create()->id_user;
        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_player_user_id' => $guestId,
        ])->assertStatus(201)
            ->assertJsonPath('data.substitute_player_user_id', $guestId)
            ->assertJsonPath('data.substitute_participation_id', null);
    }

    public function test_create_with_both_substitutes_is_422(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
            'substitute_player_user_id' => User::factory()->create()->id_user,
        ])->assertStatus(422);
    }

    public function test_create_unique_conflict_same_match(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $payload = [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
            'match_id' => $this->match->id_match,
        ];
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", $payload)
            ->assertStatus(201);
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", $payload)
            ->assertStatus(422);
    }

    public function test_create_unique_conflict_both_match_id_null(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $payload = [
            'original_participation_id' => $this->participations[0]->id_participation,
        ];
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", $payload)
            ->assertStatus(201);
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", $payload)
            ->assertStatus(422);
    }

    public function test_patch_omit_keeps_unspecified_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
            'match_id' => $this->match->id_match,
        ]);
        $create->assertStatus(201);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/event-division-replacements/{$id}", [
            'original_participation_id' => $this->participations[2]->id_participation,
        ])->assertOk()
            ->assertJsonPath('data.original_participation_id', $this->participations[2]->id_participation)
            ->assertJsonPath('data.substitute_participation_id', $this->participations[1]->id_participation)
            ->assertJsonPath('data.match_id', $this->match->id_match);
    }

    public function test_patch_one_substitute_id_nulls_the_other_type(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $guestId = User::factory()->create()->id_user;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
        ]);
        $create->assertStatus(201);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/event-division-replacements/{$id}", [
            'substitute_player_user_id' => $guestId,
        ])->assertOk()
            ->assertJsonPath('data.substitute_player_user_id', $guestId)
            ->assertJsonPath('data.substitute_participation_id', null);
    }

    public function test_patch_both_substitute_ids_null_is_unknown(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/event-division-replacements/{$id}", [
            'substitute_participation_id' => null,
            'substitute_player_user_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.substitute_participation_id', null)
            ->assertJsonPath('data.substitute_player_user_id', null);
    }

    public function test_patch_match_id_null_is_division_wide(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'match_id' => $this->match->id_match,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/event-division-replacements/{$id}", [
            'match_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.match_id', null);
    }

    public function test_patch_unique_conflict_is_422(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'match_id' => $this->match->id_match,
        ])->assertStatus(201);

        $other = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[1]->id_participation,
            'match_id' => $this->match->id_match,
        ]);
        $other->assertStatus(201);
        $id = (int) $other->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/event-division-replacements/{$id}", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ])->assertStatus(422);
    }

    public function test_patch_blocked_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk();

        $this->patchJson("/api/v1/manager/event-division-replacements/{$id}", [
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);
    }

    public function test_patch_blocked_when_replacements_not_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->schedule->update(['allow_replacements' => false]);

        $this->patchJson("/api/v1/manager/event-division-replacements/{$id}", [
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);
    }

    public function test_delete_when_replacements_not_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->schedule->update(['allow_replacements' => false]);

        $this->deleteJson("/api/v1/manager/event-division-replacements/{$id}")
            ->assertOk();
        $this->assertDatabaseMissing('event_division_replacements', [
            'id_replacement' => $id,
        ]);
    }

    public function test_replacement_nested_players_include_collecting_aware_pdf_avatar(): void
    {
        Sanctum::actingAs($this->manager);

        $original = $this->participations[0];
        $substitute = $this->participations[1];
        $original->update(['is_collecting_points' => true]);
        $substitute->update(['is_collecting_points' => false]);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $original->player_user_id,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $original->player_user_id . '/true.png',
        ]);
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $substitute->player_user_id,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $substitute->player_user_id . '/true-only.png',
        ]);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $original->id_participation,
            'substitute_participation_id' => $substitute->id_participation,
        ])->assertStatus(201);

        $data = $create->json('data');
        $this->assertTrue($data['original_participation']['is_collecting_points']);
        $this->assertFalse($data['substitute_participation']['is_collecting_points']);
        $this->assertNotNull($data['original_participation']['player']['pdf_avatar_url']);
        $this->assertNull($data['substitute_participation']['player']['pdf_avatar_url']);
    }

    public function test_guest_substitute_player_uses_collecting_true_avatar(): void
    {
        Sanctum::actingAs($this->manager);

        $guest = User::factory()->create();
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $guest->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $guest->id_user . '/guest.png',
        ]);

        $edId = $this->eventDivision->id_event_division;
        $create = $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_player_user_id' => $guest->id_user,
        ])->assertStatus(201);

        $this->assertNotNull($create->json('data.substitute_player.pdf_avatar_url'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use App\Models\Player;
use App\Services\PlayerEditionHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerEditionHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerEditionHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerEditionHistoryService();
    }

    public function test_date_cutoff_excludes_same_date_and_future(): void
    {
        $player = Player::factory()->create();
        $cutoff = '2026-06-15';

        $pastEd = $this->makeEditionWithDivision('2026-06-14', 'Past ED');
        $sameEd = $this->makeEditionWithDivision('2026-06-15', 'Same ED');
        $futureEd = $this->makeEditionWithDivision('2026-06-16', 'Future ED');

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $pastEd['edition']->id_edition,
            'edition_division_id' => $pastEd['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $sameEd['edition']->id_edition,
            'edition_division_id' => $sameEd['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $futureEd['edition']->id_edition,
            'edition_division_id' => $futureEd['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $history = $this->service->forPlayers([$player->id_player], $cutoff)[$player->id_player];

        $this->assertSame(1, $history['participated_count']);
        $this->assertSame('Past ED', $history['last_played']['division_title']);
    }

    public function test_empty_ban_reason_is_clean_not_banned(): void
    {
        $player = Player::factory()->create();
        $past = $this->makeEditionWithDivision('2026-01-01', 'Clean Ban Empty');

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => '',
        ]);

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertSame(1, $history['participated_count']);
        $this->assertSame(0, $history['banned_count']);
        $this->assertNotNull($history['last_played']);
    }

    public function test_named_ban_increments_banned_not_participated(): void
    {
        $player = Player::factory()->create();
        $past = $this->makeEditionWithDivision('2026-01-01', 'Banned ED');

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => 'AGGRESSIVE PLAYER',
        ]);

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertSame(0, $history['participated_count']);
        $this->assertSame(1, $history['banned_count']);
        $this->assertNull($history['last_played']);
    }

    public function test_absence_increments_absented(): void
    {
        $player = Player::factory()->create();
        $past = $this->makeEditionWithDivision('2026-01-01', 'Absent ED');

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => 'SICK',
            'ban_reason' => null,
        ]);

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertSame(0, $history['participated_count']);
        $this->assertSame(1, $history['absented_count']);
        $this->assertNull($history['last_played']);
    }

    public function test_last_played_picks_latest_date_then_higher_id(): void
    {
        $player = Player::factory()->create();
        $older = $this->makeEditionWithDivision('2025-01-01', 'Older');
        $newerA = $this->makeEditionWithDivision('2025-06-01', 'Newer A');
        $newerB = $this->makeEditionWithDivision('2025-06-01', 'Newer B');

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $older['edition']->id_edition,
            'edition_division_id' => $older['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        $pA = EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $newerA['edition']->id_edition,
            'edition_division_id' => $newerA['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        $pB = EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $newerB['edition']->id_edition,
            'edition_division_id' => $newerB['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $this->assertGreaterThan($pA->id_participation, $pB->id_participation);
        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertSame('Newer B', $history['last_played']['division_title']);
        $this->assertSame(3, $history['participated_count']);
    }

    public function test_rank_present_and_missing_and_participant_count_all_rows(): void
    {
        $player = Player::factory()->create();
        $other = Player::factory()->create();
        $past = $this->makeEditionWithDivision('2025-03-01', 'Ranked ED');

        $clean = EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        EditionParticipation::factory()->create([
            'player_id' => $other->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => 'SICK',
            'ban_reason' => null,
        ]);

        EditionDivisionStanding::create([
            'edition_division_id' => $past['ed']->id_edition_division,
            'participation_id' => $clean->id_participation,
            'points_total' => 10,
            'wins' => 2,
            'rank' => 3,
        ]);

        $withRank = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];
        $this->assertSame(3, $withRank['last_played']['rank']);
        $this->assertSame(2, $withRank['last_played']['participant_count']);

        $noStandingEd = $this->makeEditionWithDivision('2025-04-01', 'No Standing');
        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $noStandingEd['edition']->id_edition,
            'edition_division_id' => $noStandingEd['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $noRank = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];
        $this->assertSame('No Standing', $noRank['last_played']['division_title']);
        $this->assertNull($noRank['last_played']['rank']);
    }

    public function test_batch_shape_for_two_players_and_no_winner_key(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $past = $this->makeEditionWithDivision('2025-01-01', 'Batch ED');

        EditionParticipation::factory()->create([
            'player_id' => $p1->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        EditionParticipation::factory()->create([
            'player_id' => $p2->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => 'LEFT SUDDENLY',
        ]);

        $histories = $this->service->forPlayers([$p1->id_player, $p2->id_player], '2026-06-15');

        $this->assertSame(1, $histories[$p1->id_player]['participated_count']);
        $this->assertSame(0, $histories[$p2->id_player]['participated_count']);
        $this->assertSame(1, $histories[$p2->id_player]['banned_count']);
        $this->assertArrayNotHasKey('winner', $histories[$p1->id_player]);
        $this->assertArrayNotHasKey('is_winner', $histories[$p1->id_player]);
        if ($histories[$p1->id_player]['last_played'] !== null) {
            $this->assertArrayNotHasKey('winner', $histories[$p1->id_player]['last_played']);
        }
    }

    /**
     * @return array{edition: Edition, ed: EditionDivision}
     */
    private function makeEditionWithDivision(string $dateYmd, string $title): array
    {
        $edition = Edition::factory()->create(['date' => $dateYmd]);
        $division = Division::factory()->create();
        $ed = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
            'division_title' => $title,
        ]);

        return ['edition' => $edition, 'ed' => $ed];
    }
}

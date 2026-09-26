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

    public function test_last_played_includes_division_id(): void
    {
        $player = Player::factory()->create();
        $past = $this->makeEditionWithDivision('2025-01-01', 'Division ED');

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertSame(
            (int) $past['ed']->division_id,
            $history['last_played']['division_id']
        );
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

    public function test_tip_lists_exclude_participations_on_or_after_cutoff(): void
    {
        $player = Player::factory()->create();
        $cutoff = '2026-06-15';

        $past = $this->makeEditionWithDivision('2026-06-14', 'Past ED', 'Past Cup');
        $same = $this->makeEditionWithDivision('2026-06-15', 'Same ED', 'Same Cup');
        $future = $this->makeEditionWithDivision('2026-06-16', 'Future ED', 'Future Cup');

        foreach ([$past, $same, $future] as $bundle) {
            EditionParticipation::factory()->create([
                'player_id' => $player->id_player,
                'edition_id' => $bundle['edition']->id_edition,
                'edition_division_id' => $bundle['ed']->id_edition_division,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }

        $history = $this->service->forPlayers([$player->id_player], $cutoff)[$player->id_player];

        $this->assertCount(1, $history['played_recent']);
        $this->assertSame('Past Cup', $history['played_recent'][0]['edition_name']);
        $this->assertSame(
            ['Past Cup'],
            array_map(
                static fn (array $line): string => $line['edition_name'],
                array_values(array_filter(
                    $history['participated_tooltip']['lines'],
                    static fn (array $line): bool => ($line['type'] ?? '') === 'entry'
                ))
            )
        );
    }

    public function test_ban_and_absence_excluded_from_played_and_participated_tips(): void
    {
        $player = Player::factory()->create();
        $clean = $this->makeEditionWithDivision('2025-01-01', 'Clean', 'Clean Cup');
        $banned = $this->makeEditionWithDivision('2025-02-01', 'Banned', 'Ban Cup');
        $absent = $this->makeEditionWithDivision('2025-03-01', 'Absent', 'Absent Cup');

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $clean['edition']->id_edition,
            'edition_division_id' => $clean['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $banned['edition']->id_edition,
            'edition_division_id' => $banned['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => 'AGGRESSIVE PLAYER',
        ]);
        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $absent['edition']->id_edition,
            'edition_division_id' => $absent['ed']->id_edition_division,
            'absence_reason' => 'SICK',
            'ban_reason' => null,
        ]);

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertCount(1, $history['played_recent']);
        $this->assertSame('Clean Cup', $history['played_recent'][0]['edition_name']);
        $this->assertCount(1, $history['participated_tooltip']['lines']);
        $this->assertSame('Clean Cup', $history['participated_tooltip']['lines'][0]['edition_name']);
        $this->assertCount(1, $history['banned_recent']);
        $this->assertSame('AGGRESSIVE PLAYER', $history['banned_recent'][0]['ban_reason']);
        $this->assertCount(1, $history['absented_recent']);
        $this->assertSame('SICK', $history['absented_recent'][0]['absence_reason']);
    }

    public function test_played_recent_caps_at_four_asc_and_matches_last_played(): void
    {
        $player = Player::factory()->create();
        $titles = [];
        for ($i = 1; $i <= 5; $i++) {
            $bundle = $this->makeEditionWithDivision(
                sprintf('2025-0%d-01', $i),
                "Div {$i}",
                "Cup {$i}"
            );
            $titles[] = "Cup {$i}";
            $p = EditionParticipation::factory()->create([
                'player_id' => $player->id_player,
                'edition_id' => $bundle['edition']->id_edition,
                'edition_division_id' => $bundle['ed']->id_edition_division,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
            EditionDivisionStanding::create([
                'edition_division_id' => $bundle['ed']->id_edition_division,
                'participation_id' => $p->id_participation,
                'points_total' => $i,
                'wins' => 1,
                'rank' => $i,
            ]);
        }

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertCount(4, $history['played_recent']);
        $this->assertSame(
            ['Cup 2', 'Cup 3', 'Cup 4', 'Cup 5'],
            array_column($history['played_recent'], 'edition_name')
        );
        $last = $history['played_recent'][3];
        $this->assertSame($history['last_played']['division_title'], $last['division_title']);
        $this->assertSame($history['last_played']['rank'], $last['rank']);
        $this->assertSame($history['last_played']['participant_count'], $last['participant_count']);
    }

    public function test_participated_tooltip_truncates_with_ellipsis_when_more_than_six(): void
    {
        $player = Player::factory()->create();
        for ($i = 1; $i <= 7; $i++) {
            $bundle = $this->makeEditionWithDivision(
                sprintf('2025-%02d-01', $i),
                "Div {$i}",
                "Cup {$i}"
            );
            EditionParticipation::factory()->create([
                'player_id' => $player->id_player,
                'edition_id' => $bundle['edition']->id_edition,
                'edition_division_id' => $bundle['ed']->id_edition_division,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];
        $lines = $history['participated_tooltip']['lines'];

        $this->assertCount(7, $lines);
        $this->assertSame('entry', $lines[0]['type']);
        $this->assertSame('Cup 1', $lines[0]['edition_name']);
        $this->assertSame('Cup 2', $lines[1]['edition_name']);
        $this->assertSame('Cup 3', $lines[2]['edition_name']);
        $this->assertSame(['type' => 'ellipsis'], $lines[3]);
        $this->assertSame('Cup 5', $lines[4]['edition_name']);
        $this->assertSame('Cup 6', $lines[5]['edition_name']);
        $this->assertSame('Cup 7', $lines[6]['edition_name']);
    }

    public function test_participated_tooltip_lists_all_when_six_or_fewer(): void
    {
        $player = Player::factory()->create();
        for ($i = 1; $i <= 3; $i++) {
            $bundle = $this->makeEditionWithDivision(
                sprintf('2025-0%d-01', $i),
                "Div {$i}",
                "Cup {$i}"
            );
            EditionParticipation::factory()->create([
                'player_id' => $player->id_player,
                'edition_id' => $bundle['edition']->id_edition,
                'edition_division_id' => $bundle['ed']->id_edition_division,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];
        $lines = $history['participated_tooltip']['lines'];

        $this->assertCount(3, $lines);
        $this->assertSame(
            ['Cup 1', 'Cup 2', 'Cup 3'],
            array_column($lines, 'edition_name')
        );
        foreach ($lines as $line) {
            $this->assertSame('entry', $line['type']);
        }
    }

    public function test_banned_and_absented_recent_cap_at_four_asc_with_reasons(): void
    {
        $player = Player::factory()->create();
        for ($i = 1; $i <= 5; $i++) {
            $bundle = $this->makeEditionWithDivision(
                sprintf('2025-0%d-01', $i),
                "Ban Div {$i}",
                "Ban Cup {$i}"
            );
            EditionParticipation::factory()->create([
                'player_id' => $player->id_player,
                'edition_id' => $bundle['edition']->id_edition,
                'edition_division_id' => $bundle['ed']->id_edition_division,
                'absence_reason' => null,
                'ban_reason' => 'LEFT SUDDENLY',
            ]);
        }
        for ($i = 1; $i <= 5; $i++) {
            $bundle = $this->makeEditionWithDivision(
                sprintf('2024-0%d-01', $i),
                "Abs Div {$i}",
                "Abs Cup {$i}"
            );
            EditionParticipation::factory()->create([
                'player_id' => $player->id_player,
                'edition_id' => $bundle['edition']->id_edition,
                'edition_division_id' => $bundle['ed']->id_edition_division,
                'absence_reason' => 'FORGOT',
                'ban_reason' => null,
            ]);
        }

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertCount(4, $history['banned_recent']);
        $this->assertSame(
            ['Ban Cup 2', 'Ban Cup 3', 'Ban Cup 4', 'Ban Cup 5'],
            array_column($history['banned_recent'], 'edition_name')
        );
        $this->assertSame('LEFT SUDDENLY', $history['banned_recent'][0]['ban_reason']);

        $this->assertCount(4, $history['absented_recent']);
        $this->assertSame(
            ['Abs Cup 2', 'Abs Cup 3', 'Abs Cup 4', 'Abs Cup 5'],
            array_column($history['absented_recent'], 'edition_name')
        );
        $this->assertSame('FORGOT', $history['absented_recent'][0]['absence_reason']);
    }

    public function test_played_recent_null_rank_when_no_standing(): void
    {
        $player = Player::factory()->create();
        $past = $this->makeEditionWithDivision('2025-01-01', 'No Rank', 'No Rank Cup');
        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $past['edition']->id_edition,
            'edition_division_id' => $past['ed']->id_edition_division,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $history = $this->service->forPlayers([$player->id_player], '2026-06-15')[$player->id_player];

        $this->assertNull($history['played_recent'][0]['rank']);
        $this->assertNull($history['last_played']['rank']);
    }

    /**
     * @return array{edition: Edition, ed: EditionDivision}
     */
    private function makeEditionWithDivision(string $dateYmd, string $title, ?string $editionName = null): array
    {
        $edition = Edition::factory()->create([
            'date' => $dateYmd,
            'name' => $editionName ?? $title,
        ]);
        $division = Division::factory()->create();
        $ed = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
            'division_title' => $title,
        ]);

        return ['edition' => $edition, 'ed' => $ed];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\Player;
use App\Services\Schedule\RoundClockCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EditionDivisionScheduleReorderTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private EditionDivision $editionDivision;

    private EditionDivisionCourt $courtA;

    private EditionDivisionCourt $courtB;

    /** @var list<EditionParticipation> */
    private array $participations = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $edition = Edition::factory()->create();
        $division = Division::factory()->create();

        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
            'players_limit' => 8,
            'playing_start_time' => '14:00:00',
            'playing_end_time' => '17:00:00',
            'player_opponents_nr' => 4,
            'player_matches_repeat' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
            'include_scores' => true,
            'allow_replacements' => true,
            'points_per_match' => 16,
            'template_version' => 4,
        ]);

        $this->courtA = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'court_id' => Court::factory()->create(['name' => 'Court A'])->id_court,
            'display_order' => 0,
        ]);
        $this->courtB = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'court_id' => Court::factory()->create(['name' => 'Court B'])->id_court,
            'display_order' => 1,
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->participations[] = EditionParticipation::factory()->create([
                'edition_id' => $edition->id_edition,
                'edition_division_id' => $this->editionDivision->id_edition_division,
                'player_id' => Player::factory()->create()->id_player,
                'sort_order' => $i,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }
    }

    public function test_reorder_syncs_complete_rounds_recomputes_clocks_and_clears_matches_posters(): void
    {
        Sanctum::actingAs($this->manager);
        $edId = $this->editionDivision->id_edition_division;

        $seeded = $this->seedSparseTwoCourtSchedule();
        $refComplete = $seeded['a_complete'];
        $sparseA = $seeded['a_sparse'];
        $bComplete = $seeded['b_complete'];

        $poster = $this->makeMatchesPoster();
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $poster->generated_path, '%PDF');
        $poster->printed_path = 'printed/keep.pdf';
        $poster->save();
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put('printed/keep.pdf', 'keep');

        $beforeSparseIds = $sparseA->pluck('id_match')->all();
        $beforeSparseSeq = $sparseA->pluck('sequence_no')->all();
        $beforeSides = $this->sideFingerprint($refComplete->first());
        $beforePoints = (int) $refComplete->first()->points_target;

        $newOrder = $refComplete->reverse()->values();
        $matchIds = $newOrder->pluck('id_match')->map(static fn ($id): int => (int) $id)->all();

        $response = $this->postJson(
            "/api/v1/manager/edition-divisions/{$edId}/schedule/matches/reorder",
            [
                'court' => $this->courtA->id_edition_division_court,
                'match_ids' => $matchIds,
            ]
        );
        $response->assertOk();

        $refAfter = GameMatch::query()
            ->where('edition_division_court_id', $this->courtA->id_edition_division_court)
            ->where('match_kind', 'REGULAR')
            ->orderBy('sequence_no')
            ->get();
        $otherAfter = GameMatch::query()
            ->where('edition_division_court_id', $this->courtB->id_edition_division_court)
            ->where('match_kind', 'REGULAR')
            ->orderBy('sequence_no')
            ->get();

        $completeCount = 3;
        for ($i = 0; $i < $completeCount; $i++) {
            $this->assertSame((int) $newOrder[$i]->id_match, (int) $refAfter[$i]->id_match);
            $this->assertSame($i + 1, (int) $refAfter[$i]->sequence_no);
        }

        $expectedBOrder = $bComplete->reverse()->values();
        for ($i = 0; $i < $completeCount; $i++) {
            $this->assertSame((int) $expectedBOrder[$i]->id_match, (int) $otherAfter[$i]->id_match);
            $this->assertSame($i + 1, (int) $otherAfter[$i]->sequence_no);
        }

        $sparseAfter = $refAfter->slice($completeCount)->values();
        $this->assertSame($beforeSparseIds, $sparseAfter->pluck('id_match')->all());
        $this->assertSame($beforeSparseSeq, $sparseAfter->pluck('sequence_no')->all());

        $roundsTotal = 4;
        $slots = RoundClockCalculator::slots('14:00', '17:00', $roundsTotal, false, false);
        foreach ($refAfter as $match) {
            $slot = $slots[(int) $match->sequence_no - 1];
            $start = $match->scheduled_start;
            $end = $match->scheduled_end;
            $startStr = is_object($start) && method_exists($start, 'format') ? $start->format('H:i') : substr((string) $start, 0, 5);
            $endStr = is_object($end) && method_exists($end, 'format') ? $end->format('H:i') : substr((string) $end, 0, 5);
            $this->assertSame($slot['start'], $startStr);
            $this->assertSame($slot['end'], $endStr);
        }

        $this->assertSame($beforeSides, $this->sideFingerprint($refAfter->first()));
        $this->assertSame($beforePoints, (int) $refAfter->first()->points_target);

        $this->assertNull($poster->fresh()->generated_path);
        $this->assertSame('printed/keep.pdf', $poster->fresh()->printed_path);
        $this->assertIsArray($response->json('data.board_metrics.playing_distribution'));
    }

    public function test_reorder_409_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);
        $this->seedSparseTwoCourtSchedule();
        $this->editionDivision->edition->update(['finalized_at' => time()]);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/matches/reorder",
            [
                'court' => $this->courtA->id_edition_division_court,
                'match_ids' => $this->completeMatchIds(),
            ]
        )->assertStatus(409);
    }

    public function test_reorder_409_when_scores_exist(): void
    {
        Sanctum::actingAs($this->manager);
        $seeded = $this->seedSparseTwoCourtSchedule();
        MatchSide::query()->where('match_id', $seeded['a_complete']->first()->id_match)->update(['score' => 5]);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/matches/reorder",
            [
                'court' => $this->courtA->id_edition_division_court,
                'match_ids' => $this->completeMatchIds(),
            ]
        )->assertStatus(409);
    }

    public function test_reorder_409_when_standings_exist(): void
    {
        Sanctum::actingAs($this->manager);
        $this->seedSparseTwoCourtSchedule();
        EditionDivisionStanding::create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'participation_id' => $this->participations[0]->id_participation,
            'points_total' => 0,
            'wins' => 0,
            'rank' => 1,
        ]);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/matches/reorder",
            [
                'court' => $this->courtA->id_edition_division_court,
                'match_ids' => $this->completeMatchIds(),
            ]
        )->assertStatus(409);
    }

    public function test_reorder_422_when_sparse_id_in_payload(): void
    {
        Sanctum::actingAs($this->manager);
        $seeded = $this->seedSparseTwoCourtSchedule();
        $ids = $this->completeMatchIds();
        $ids[0] = (int) $seeded['a_sparse']->first()->id_match;

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/matches/reorder",
            [
                'court' => $this->courtA->id_edition_division_court,
                'match_ids' => $ids,
            ]
        )->assertStatus(422);
    }

    public function test_reorder_422_when_incomplete_complete_set(): void
    {
        Sanctum::actingAs($this->manager);
        $this->seedSparseTwoCourtSchedule();
        $ids = $this->completeMatchIds();
        array_pop($ids);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/matches/reorder",
            [
                'court' => $this->courtA->id_edition_division_court,
                'match_ids' => $ids,
            ]
        )->assertStatus(422);
    }

    /**
     * Court A: sequences 1..4 (last sparse); Court B: sequences 1..3.
     *
     * @return array{a_complete: \Illuminate\Support\Collection, a_sparse: \Illuminate\Support\Collection, b_complete: \Illuminate\Support\Collection}
     */
    private function seedSparseTwoCourtSchedule(): array
    {
        $aComplete = collect();
        for ($seq = 1; $seq <= 3; $seq++) {
            $aComplete->push($this->createRegularMatch($this->courtA, $seq, [0, 1, 2, 3]));
        }
        $aSparse = collect([$this->createRegularMatch($this->courtA, 4, [0, 1, 2, 3])]);

        $bComplete = collect();
        for ($seq = 1; $seq <= 3; $seq++) {
            $bComplete->push($this->createRegularMatch($this->courtB, $seq, [4, 5, 6, 7]));
        }

        return [
            'a_complete' => $aComplete,
            'a_sparse' => $aSparse,
            'b_complete' => $bComplete,
        ];
    }

    /**
     * @param  list<int>  $participationIndexes
     */
    private function createRegularMatch(EditionDivisionCourt $court, int $sequenceNo, array $participationIndexes): GameMatch
    {
        $match = GameMatch::create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'edition_division_court_id' => $court->id_edition_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => $sequenceNo,
            'scheduled_start' => '14:00:00',
            'scheduled_end' => '14:45:00',
            'points_target' => 16,
        ]);

        $left = MatchSide::create(['match_id' => $match->id_match, 'side' => 'LEFT', 'score' => null]);
        $right = MatchSide::create(['match_id' => $match->id_match, 'side' => 'RIGHT', 'score' => null]);

        $slots = [
            [$left, 0, $participationIndexes[0]],
            [$left, 1, $participationIndexes[1]],
            [$right, 0, $participationIndexes[2]],
            [$right, 1, $participationIndexes[3]],
        ];
        foreach ($slots as [$side, $slot, $pIdx]) {
            $p = $this->participations[$pIdx];
            MatchPlayer::create([
                'match_side_id' => $side->id_match_side,
                'slot' => $slot,
                'participation_id' => $p->id_participation,
                'player_id' => $p->player_id,
                'got_replaced' => false,
            ]);
        }

        return $match->fresh(['sides.players']);
    }

    /**
     * @return list<int>
     */
    private function completeMatchIds(): array
    {
        return GameMatch::query()
            ->where('edition_division_court_id', $this->courtA->id_edition_division_court)
            ->where('match_kind', 'REGULAR')
            ->where('sequence_no', '<=', 3)
            ->orderBy('sequence_no')
            ->pluck('id_match')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function makeMatchesPoster(): EditionDivisionPoster
    {
        $poster = new EditionDivisionPoster();
        $poster->edition_division_id = $this->editionDivision->id_edition_division;
        $poster->edition_division_court_id = $this->courtA->id_edition_division_court;
        $poster->poster_kind = 'MATCHES';
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'edition-division-posters/' . $poster->id_edition_division_poster . '/generated/m.pdf';
        $poster->save();

        return $poster;
    }

    private function sideFingerprint(GameMatch $match): string
    {
        $match->load('sides.players');
        $parts = [];
        foreach ($match->sides->sortBy('side') as $side) {
            $ids = $side->players->sortBy('slot')->pluck('participation_id')->all();
            $parts[] = $side->side . ':' . implode(',', $ids);
        }

        return implode('|', $parts);
    }
}

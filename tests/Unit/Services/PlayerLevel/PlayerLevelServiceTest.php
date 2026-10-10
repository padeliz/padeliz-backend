<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerLevel;

use App\Models\EventParticipation;
use App\Models\Player;
use App\Models\PlayerLevel;
use App\Models\PlayerLevelAssessment;
use App\Services\PlayerLevel\PlayerLevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PlayerLevelServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerLevelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlayerLevelService::class);
    }

    public function test_current_level_is_null_without_history(): void
    {
        $player = Player::factory()->create();

        $this->assertNull($this->service->currentLevel((int) $player->id_player));
    }

    public function test_current_level_takes_the_newest_row_and_breaks_ties_on_id(): void
    {
        $player = Player::factory()->create();

        $older = $this->seedLevel($player, '2.50', 1_700_000_000);
        $tieA = $this->seedLevel($player, '3.50', 1_700_000_100);
        $tieB = $this->seedLevel($player, '4.50', 1_700_000_100);

        $current = $this->service->currentLevel((int) $player->id_player);

        $this->assertNotNull($current);
        $this->assertSame(
            (int) $tieB->id_player_level,
            (int) $current->id_player_level,
            'The highest id wins when inserted_at ties.'
        );
        $this->assertNotSame((int) $older->id_player_level, (int) $current->id_player_level);
        $this->assertNotSame((int) $tieA->id_player_level, (int) $current->id_player_level);
    }

    public function test_upsert_for_assessment_creates_then_updates_the_same_row(): void
    {
        $player = Player::factory()->create();
        $assessment = PlayerLevelAssessment::factory()->forPlayer($player)->approved()->create();

        $created = $this->service->upsertForAssessment(
            (int) $player->id_player,
            (int) $assessment->id_player_level_assessment,
            '3.50'
        );

        $this->assertSame('3.50', $created->level);
        $this->assertNull($created->event_participation_id);

        $updated = $this->service->upsertForAssessment(
            (int) $player->id_player,
            (int) $assessment->id_player_level_assessment,
            '4.20'
        );

        $this->assertSame((int) $created->id_player_level, (int) $updated->id_player_level);
        $this->assertSame('4.20', $updated->level);
        $this->assertSame(1, PlayerLevel::query()->where('player_id', $player->id_player)->count());
    }

    public function test_upsert_for_participation_creates_then_updates_the_same_row(): void
    {
        $participation = EventParticipation::factory()->create();
        $playerId = (int) $participation->player_id;

        $created = $this->service->upsertForParticipation(
            $playerId,
            (int) $participation->id_participation,
            '2.10'
        );

        $this->assertNull($created->player_level_assessment_id);
        $this->assertSame('2.10', $created->level);

        $updated = $this->service->upsertForParticipation(
            $playerId,
            (int) $participation->id_participation,
            '2.40'
        );

        $this->assertSame((int) $created->id_player_level, (int) $updated->id_player_level);
        $this->assertSame('2.40', $updated->level);
    }

    public function test_level_must_have_exactly_two_decimals(): void
    {
        $this->assertSame('3.50', $this->service->normalizeLevel(3.5));
        $this->assertSame('3.50', $this->service->normalizeLevel('3.50'));

        $this->expectException(ValidationException::class);
        $this->service->normalizeLevel('3.5');
    }

    public function test_level_outside_a_seeded_band_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->normalizeLevel('9.99');
    }

    public function test_band_for_resolves_the_seeded_band(): void
    {
        $this->assertSame('Division 3 Mid', $this->service->bandFor('3.50')?->name);
        $this->assertSame('Division 5 Low', $this->service->bandFor('5.99')?->name);
        $this->assertNull($this->service->bandFor('6.50'));
    }

    public function test_a_level_must_have_exactly_one_source(): void
    {
        $this->service->assertSingleSource(1, null);
        $this->service->assertSingleSource(null, 1);

        $this->expectException(ValidationException::class);
        $this->service->assertSingleSource(1, 1);
    }

    public function test_a_level_without_any_source_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->assertSingleSource(null, null);
    }

    public function test_persisted_levels_report_exactly_one_source(): void
    {
        $participation = EventParticipation::factory()->create();

        $fromParticipation = $this->service->upsertForParticipation(
            (int) $participation->player_id,
            (int) $participation->id_participation,
            '2.10'
        );

        $this->assertTrue($fromParticipation->hasExactlyOneSource());
    }

    private function seedLevel(Player $player, string $level, int $insertedAt): PlayerLevel
    {
        $assessment = PlayerLevelAssessment::factory()->forPlayer($player)->approved()->create();

        return PlayerLevel::factory()
            ->fromAssessment($assessment)
            ->level($level)
            ->create(['inserted_at' => $insertedAt]);
    }
}

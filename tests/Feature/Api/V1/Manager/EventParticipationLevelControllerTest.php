<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventDivisionStanding;
use App\Models\EventParticipation;
use App\Models\Player;
use App\Models\PlayerLevel;
use App\Services\PlayerLevel\PlayerLevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EventParticipationLevelControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/manager/event-participations';

    private EventParticipation $participation;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));
        $this->participation = EventParticipation::factory()->create();
    }

    private function url(?EventParticipation $participation = null): string
    {
        return self::BASE . '/' . ($participation ?? $this->participation)->id_participation . '/level';
    }

    public function test_show_returns_404_until_a_level_is_entered(): void
    {
        $this->getJson($this->url())->assertNotFound();

        $this->postJson($this->url(), ['level' => '3.50'])->assertOk();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.level', '3.50');
    }

    public function test_post_creates_a_participation_sourced_level(): void
    {
        $this->postJson($this->url(), ['level' => '3.50'])
            ->assertOk()
            ->assertJsonPath('data.level', '3.50')
            ->assertJsonPath('data.player_id', $this->participation->player_id)
            ->assertJsonPath('data.band_name', 'Division 3 Mid');

        $this->assertDatabaseHas('player_levels', [
            'player_id' => $this->participation->player_id,
            'event_participation_id' => $this->participation->id_participation,
            'player_level_assessment_id' => null,
            'level' => '3.50',
        ]);
    }

    public function test_put_and_patch_are_equivalent_and_update_in_place(): void
    {
        $this->postJson($this->url(), ['level' => '3.50'])->assertOk();
        $row = PlayerLevel::query()->where('event_participation_id', $this->participation->id_participation)->firstOrFail();
        PlayerLevel::query()->whereKey($row->getKey())->update(['inserted_at' => 1_700_000_000]);

        $this->putJson($this->url(), ['level' => '4.20'])->assertOk()->assertJsonPath('data.level', '4.20');
        $this->patchJson($this->url(), ['level' => '4.40'])->assertOk()->assertJsonPath('data.level', '4.40');

        $rows = PlayerLevel::query()->where('event_participation_id', $this->participation->id_participation)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($row->getKey(), $rows->first()->getKey());
        $this->assertSame('4.40', (string) $rows->first()->level);
        $this->assertSame(1_700_000_000, (int) $rows->first()->inserted_at);
    }

    public function test_a_numeric_level_is_normalised_to_two_decimals(): void
    {
        $this->postJson($this->url(), ['level' => 3.5])
            ->assertOk()
            ->assertJsonPath('data.level', '3.50');
    }

    public function test_each_participation_keeps_its_own_level_and_the_latest_one_is_current(): void
    {
        $player = Player::factory()->create();
        $first = EventParticipation::factory()->create(['player_id' => $player->id_player]);
        $second = EventParticipation::factory()->create(['player_id' => $player->id_player]);

        $this->postJson($this->url($first), ['level' => '2.50'])->assertOk();
        PlayerLevel::query()->where('event_participation_id', $first->id_participation)
            ->update(['inserted_at' => time() - 5000]);
        $this->postJson($this->url($second), ['level' => '4.50'])->assertOk();

        // Re-setting the older participation must not overtake the newer one.
        $this->postJson($this->url($first), ['level' => '2.60'])->assertOk();

        $this->assertSame(2, PlayerLevel::query()->where('player_id', $player->id_player)->count());
        $this->assertSame('4.50', (string) app(PlayerLevelService::class)->currentLevel((int) $player->id_player)->level);
    }

    public function test_invalid_levels_are_rejected(): void
    {
        $this->postJson($this->url(), [])->assertStatus(422);
        $this->postJson($this->url(), ['level' => 'abc'])->assertStatus(422);
        $this->postJson($this->url(), ['level' => '3.5'])->assertStatus(422);
        $this->postJson($this->url(), ['level' => '3.555'])->assertStatus(422);
        $this->postJson($this->url(), ['level' => '99.00'])->assertStatus(422);

        $this->assertSame(0, PlayerLevel::query()->count());
    }

    public function test_unknown_participation_is_404(): void
    {
        $this->postJson(self::BASE . '/999999/level', ['level' => '3.50'])->assertNotFound();
    }

    public function test_requires_manager_role(): void
    {
        Sanctum::actingAs(Player::factory()->playerOnly()->create());

        $this->postJson($this->url(), ['level' => '3.50'])->assertStatus(403);
        $this->getJson($this->url())->assertStatus(403);
    }

    public function test_standings_index_includes_the_entered_player_level(): void
    {
        $standing = EventDivisionStanding::query()->create([
            'event_division_id' => $this->participation->event_division_id,
            'participation_id' => $this->participation->id_participation,
            'points_total' => 10,
            'wins' => 2,
            'rank' => 1,
        ]);

        $url = '/api/v1/manager/event-divisions/' . $this->participation->event_division_id . '/standings';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.id_standing', $standing->id_standing)
            ->assertJsonPath('data.0.player_level', null);

        $this->postJson($this->url(), ['level' => '3.50'])->assertOk();

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.player_level.level', '3.50');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Level;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class LevelControllersTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_list_and_show_levels_read_only(): void
    {
        Sanctum::actingAs(Player::factory()->create());
        $level = Level::query()->firstOrFail();

        $this->getJson('/api/v1/app/levels')
            ->assertOk()
            ->assertJsonPath('data.0.id_level', Level::query()->orderBy('level_max')->orderBy('id_level')->value('id_level'));
        $this->getJson('/api/v1/app/levels/' . $level->id_level)
            ->assertOk()
            ->assertJsonPath('data.name', $level->name);
        $this->postJson('/api/v1/app/levels', [])->assertStatus(405);
        $this->deleteJson('/api/v1/app/levels/' . $level->id_level)->assertStatus(405);
    }

    public function test_manager_can_list_and_show_levels_read_only(): void
    {
        Sanctum::actingAs(Player::factory()->manager()->create(['user_types' => ['player', 'manager']]));
        $level = Level::query()->firstOrFail();

        $this->getJson('/api/v1/manager/levels?per_page=100')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/v1/manager/levels/' . $level->id_level)
            ->assertOk()
            ->assertJsonPath('data.name', $level->name);
        $this->putJson('/api/v1/manager/levels/' . $level->id_level, [])->assertStatus(405);
        $this->postJson('/api/v1/manager/levels', [])->assertStatus(405);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Player;
use App\Models\PlayerEligibility;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerEligibilityPendingUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_enforces_only_one_pending_per_player(): void
    {
        $player = Player::factory()->playerOnly()->create();

        PlayerEligibility::factory()->create([
            'player_id' => $player->id_player,
            'review_status' => 'pending',
        ]);

        $this->expectException(QueryException::class);

        PlayerEligibility::factory()->create([
            'player_id' => $player->id_player,
            'review_status' => 'pending',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Eligibility;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibilityPendingUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_enforces_only_one_pending_per_player(): void
    {
        $player = Player::factory()->playerOnly()->create();

        Eligibility::factory()->create([
            'player_id' => $player->id_player,
            'review_status' => 'pending',
        ]);

        $this->expectException(QueryException::class);

        Eligibility::factory()->create([
            'player_id' => $player->id_player,
            'review_status' => 'pending',
        ]);
    }
}

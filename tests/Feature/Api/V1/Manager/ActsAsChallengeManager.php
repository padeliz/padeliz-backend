<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use Laravel\Sanctum\Sanctum;

trait ActsAsChallengeManager
{
    protected function actingAsManager(): Player
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        return $manager;
    }
}

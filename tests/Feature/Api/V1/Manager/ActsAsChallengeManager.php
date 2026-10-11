<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait ActsAsChallengeManager
{
    protected function actingAsManager(): User
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        return $manager;
    }
}

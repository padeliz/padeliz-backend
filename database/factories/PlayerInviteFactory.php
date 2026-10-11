<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\PlayerInvite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlayerInviteFactory extends Factory
{
    protected $model = PlayerInvite::class;

    public function definition()
    {
        return [
            'player_user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => time() + (7 * 24 * 60 * 60),
            'accepted_at' => null,
            'revoked_at' => null,
            'created_by_manager_user_id' => null,
            'inserted_at' => time(),
        ];
    }
}

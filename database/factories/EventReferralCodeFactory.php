<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PadelEvent;
use App\Models\EventReferralCode;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventReferralCodeFactory extends Factory
{
    protected $model = EventReferralCode::class;

    public function definition()
    {
        return [
            'event_id' => PadelEvent::factory(),
            'referrer_player_id' => Player::factory(),
            'code' => Str::upper(Str::random(8)),
            'expires_at' => time() + ((int) config('referral.code_ttl_hours', 24) * 3600),
            'revoked_at' => null,
            'inserted_at' => $this->faker->numberBetween(time() - (90 * 24 * 60 * 60), time()),
        ];
    }
}

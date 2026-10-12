<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventReferralCode;
use App\Models\PadelEvent;
use App\Models\ReferralTouchpoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralTouchpointFactory extends Factory
{
    protected $model = ReferralTouchpoint::class;

    public function definition()
    {
        return [
            'referral_code_id' => EventReferralCode::factory(),
            'event_id' => fn (array $attributes) => EventReferralCode::query()
                ->whereKey($attributes['referral_code_id'])
                ->value('event_id'),
            'referred_player_user_id' => User::factory(),
            'referrer_player_user_id' => fn (array $attributes) => EventReferralCode::query()
                ->whereKey($attributes['referral_code_id'])
                ->value('referrer_player_user_id'),
            'discount_eligible' => true,
            'touched_at' => time(),
            'client_metadata' => null,
            'inserted_at' => $this->faker->numberBetween(time() - (90 * 24 * 60 * 60), time()),
        ];
    }

    public function forEvent(PadelEvent $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id_event,
        ]);
    }
}

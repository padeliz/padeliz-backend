<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventReferralCode;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\ReferralTouchpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralTouchpointFactory extends Factory
{
    protected $model = ReferralTouchpoint::class;

    public function definition()
    {
        $referralCode = EventReferralCode::factory()->create();

        return [
            'event_id' => $referralCode->event_id,
            'referred_player_user_id' => User::factory(),
            'referrer_player_user_id' => $referralCode->referrer_player_user_id,
            'referral_code_id' => $referralCode->id_referral_code,
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

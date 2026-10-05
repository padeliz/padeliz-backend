<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Referral\ReferralAttributionStatus;
use App\Models\PadelEvent;
use App\Models\EventReferralAttribution;
use App\Models\EventReferralCode;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventReferralAttributionFactory extends Factory
{
    protected $model = EventReferralAttribution::class;

    public function definition()
    {
        $referralCode = EventReferralCode::factory()->create();

        return [
            'event_id' => $referralCode->event_id,
            'referred_player_id' => Player::factory(),
            'referrer_player_id' => $referralCode->referrer_player_id,
            'referral_code_id' => $referralCode->id_referral_code,
            'checkout_id' => null,
            'status' => ReferralAttributionStatus::Pending,
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

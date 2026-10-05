<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PadelEvent;
use App\Models\EventReferralSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventReferralSettingFactory extends Factory
{
    protected $model = EventReferralSetting::class;

    public function definition()
    {
        return [
            'event_id' => PadelEvent::factory(),
            'code_ttl_hours' => $this->faker->optional()->numberBetween(1, 168),
            'inserted_at' => $this->faker->numberBetween(time() - (90 * 24 * 60 * 60), time()),
        ];
    }
}

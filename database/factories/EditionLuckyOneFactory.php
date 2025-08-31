<?php

namespace Database\Factories;

use App\Models\EditionLuckyOne;
use App\Models\EditionLottery;
use App\Models\EditionParticipation;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionLuckyOneFactory extends Factory
{
    protected $model = EditionLuckyOne::class;

    public function definition()
    {
        $drawnAt = $this->faker->dateTimeBetween('-1 hour', 'now');

        return [
            'lottery_id' => EditionLottery::factory(),
            'drawn_participation_id' => EditionParticipation::factory(),
            'drawn_at' => $drawnAt->getTimestamp(),
            'available_at' => $this->faker->dateTimeBetween($drawnAt, '+1 hour')->getTimestamp(),
            'announced_at' => ($date = $this->faker->optional()->dateTimeBetween($drawnAt, '+2 hours')) ? $date->getTimestamp() : null,
            'accepted_at' => ($date = $this->faker->optional()->dateTimeBetween($drawnAt, '+3 hours')) ? $date->getTimestamp() : null,
            'rejected_at' => ($date = $this->faker->optional()->dateTimeBetween($drawnAt, '+3 hours')) ? $date->getTimestamp() : null,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}

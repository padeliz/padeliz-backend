<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use Illuminate\Database\Eloquent\Factories\Factory;

class SponsorPrizeFactory extends Factory
{
    protected $model = SponsorPrize::class;

    public function definition()
    {
        return [
            'sponsor_id' => Sponsor::factory(),
            'prize_id' => Prize::factory(),
            'media_path' => $this->faker->optional()->passthrough('prizes-from-clubs/1/media/placeholder.jpg'),
            'template' => $this->faker->optional()->randomElement(['IMAGE_LEFT', 'IMAGE_TOP', 'IMAGE_RIGHT', 'IMAGE_MIDDLE', 'IMAGE_BOTTOM']),
            'box_1_text' => $this->faker->optional()->sentence(),
            'box_1_bg_color' => $this->faker->optional()->hexColor(),
            'box_1_text_color' => $this->faker->optional()->hexColor(),
            'box_2_text' => $this->faker->optional()->sentence(),
            'box_2_bg_color' => $this->faker->optional()->hexColor(),
            'box_2_text_color' => $this->faker->optional()->hexColor(),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}

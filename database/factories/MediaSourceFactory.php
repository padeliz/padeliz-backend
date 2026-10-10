<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaSource>
 */
class MediaSourceFactory extends Factory
{
    protected $model = MediaSource::class;

    /**
     * The migration seeds instagram/tiktok/facebook/friends, so the default code is unique-suffixed.
     */
    public function definition(): array
    {
        $code = $this->faker->unique()->lexify('source-????');

        return [
            'code' => $code,
            'label' => ucfirst($code),
            'sort_order' => $this->faker->numberBetween(5, 99),
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }
}

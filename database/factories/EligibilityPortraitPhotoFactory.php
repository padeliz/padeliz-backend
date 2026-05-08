<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Eligibility;
use App\Models\EligibilityPortraitPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EligibilityPortraitPhoto>
 */
class EligibilityPortraitPhotoFactory extends Factory
{
    protected $model = EligibilityPortraitPhoto::class;

    public function definition(): array
    {
        return [
            'eligibility_id' => Eligibility::factory(),
            'path' => 'eligibilities/1/portrait-photo/placeholder.png',
            'sort_order' => 0,
            'inserted_at' => time(),
            'updated_at' => time(),
        ];
    }
}

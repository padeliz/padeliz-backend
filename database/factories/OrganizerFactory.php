<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organizer;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizerFactory extends Factory
{
    protected $model = Organizer::class;

    public function definition()
    {
        return [
            'name' => substr($this->faker->unique()->company(), 0, 60),
            'logo_path' => null,
            'show_edition_label' => false,
            'inserted_at' => time(),
        ];
    }
}

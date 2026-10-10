<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Division;
use App\Models\DivisionLevel;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DivisionLevel>
 */
class DivisionLevelFactory extends Factory
{
    protected $model = DivisionLevel::class;

    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'level_id' => Level::factory(),
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }
}

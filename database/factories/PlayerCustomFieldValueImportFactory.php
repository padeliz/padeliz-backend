<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerCustomFieldValueImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerCustomFieldValueImport>
 */
class PlayerCustomFieldValueImportFactory extends Factory
{
    protected $model = PlayerCustomFieldValueImport::class;

    public function definition(): array
    {
        return [
            'imported_by_player_id' => Player::factory()->manager(),
            'format' => 'google_form_minitour_welcome',
            'original_filename' => 'minitour.csv',
            'edition_id' => null,
            'included_count' => 0,
            'values_new_count' => 0,
            'values_replaced_count' => 0,
            'inserted_at' => time(),
        ];
    }
}
